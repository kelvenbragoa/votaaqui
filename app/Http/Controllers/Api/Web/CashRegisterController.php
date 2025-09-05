<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\DailyStockSnapshot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockSnapshot;
use App\Models\Table;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $searchQuery = request('query');

            $categories = CashRegister::query()
            ->when(request('query'),function($query,$searchQuery){
                $query->where('name','like',"%{$searchQuery}%");
            })
            ->with('orderitens')
            ->with('user')
            ->with('status')
            ->orderBy('id','desc')
            ->paginate();

            $categories->getCollection()->transform(function ($category) {
                $category->order_itens_total = $category->orderitens->sum('total');
                return $category;
            });

            return response()->json($categories);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function open(Request $request)
{
    $data = $request->all();
    $today = now()->toDateString();

    // Verifica se o usuário já tem caixa aberto
    $existingCashRegister = CashRegister::where('user_id', Auth::id())
        ->where('cash_register_status_id', 1)
        ->first();

    if ($existingCashRegister) {
        return response()->json([
            'message' => 'Já existe um caixa aberto para este usuário.',
            'cash_register' => $existingCashRegister
        ], 400);
    }

    // Cria o caixa
    $cashRegister = CashRegister::create([
        'user_id' => Auth::id(),
        'cash_register_status_id' => 1,
        'opening_balance' => $data['opening_balance'],
        'opened_at' => now(),
    ]);

    // Carrega os produtos com stock atual
    $products = Product::withQuantityInPrincipalStock()->get();

    // Snapshot por caixa (stock visível ao vendedor)
    $snapshots = [];
    foreach ($products as $product) {
        $snapshots[] = [
            'product_id' => $product->id,
            'cash_register_id' => $cashRegister->id,
            'quantity' => $product->quantity_in_principal_stock,
            'date' => $today,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    StockSnapshot::insert($snapshots);

    // Snapshot diário geral (só se ainda não existir)
    $alreadySnapshotted = DailyStockSnapshot::where('date', $today)->exists();

    if (!$alreadySnapshotted) {
        $dailySnapshots = [];
        foreach ($products as $product) {
            $dailySnapshots[] = [
                'product_id' => $product->id,
                'quantity' => $product->quantity_in_principal_stock,
                'date' => $today,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DailyStockSnapshot::insert($dailySnapshots);
    }

    return response()->json($cashRegister);
}

    // public function open(Request $request){
    //     $data = $request->all();
    //     $existingCashRegister = CashRegister::where('user_id', Auth::user()->id)
    //     ->where('cash_register_status_id', 1)
    //     ->first();

    //     if ($existingCashRegister) {
    //         return response()->json([
    //             'message' => 'Já existe um caixa aberto para este usuário.',
    //             'cash_register' => $existingCashRegister
    //         ], 400);
    //     }

    //     $today = Carbon::today();
    //     $existingCash = CashRegister::whereDate('opened_at', $today)->first();

    //     if (!$existingCash) {
    //         // $products = Product::withQuantityInPrincipalStock()->get();
    //         // foreach ($products as $product) {
    //         //     // StockSnapshot::create([
    //         //     //     'product_id' => $product->id,
    //         //     //     'quantity' => $product->quantity_in_principal_stock,
    //         //     //     'date' => $today,
    //         //     // ]);
    //         // }
    //     }
    //     $cashregister = CashRegister::create([
    //         'user_id' => Auth::user()->id,
    //         'cash_register_status_id' => 1,
    //         'opening_balance'=>$data['opening_balance'],
    //         'opened_at' => now(),
    //     ]);

    //     return response()->json($cashregister);
    // }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        $data = $request->all();
        $existingCashRegister = CashRegister::where('user_id', Auth::user()->id)
        ->where('cash_register_status_id', 1)
        ->first();

        if ($existingCashRegister) {
            return response()->json([
                'message' => 'Já existe um caixa aberto para este usuário.',
                'cash_register' => $existingCashRegister
            ], 400);
        }
        $cashregister = CashRegister::create([
            'user_id' => Auth::user()->id,
            'cash_register_status_id' => 1,
            'opening_balance'=>$data['opening_balance'],
            'opened_at' => now(),
        ]);

        return response()->json($cashregister);
    }

    public function close(Request $request)
    {

        $data = $request->all();
        $cashRegister = CashRegister::
            where('user_id', Auth::user()->id)
            ->where('cash_register_status_id', 1) // 1 = Aberto
            ->first();

        if (!$cashRegister) {
            return response()->json([
                'message' => 'Nenhum caixa aberto encontrado para este usuário.'
            ], 404);
        }

        $order = Order::where('cash_register_id', $cashRegister->id)
        ->where(function ($query) {
            $query->where('order_status_id', 1)
                ->orWhere('order_status_id', 2);
        })
        ->first();
        if ($order) {
                return response()->json([
                    'message' => 'Existe uma encomenda que não foi finalizada. Por Favor Finalize e Feche a sua conta.'
                ], 404);
        }



        // Calcular o total de vendas realizadas durante o período do caixa
        $totalSales = OrderItem::where('cash_register_id', $cashRegister->id)
            ->sum('total');

        // Atualizar informações do caixa
        $cashRegister->update([
            'closing_balance' => $data['closing_balance'],
            'automatic_closing_balance' => $totalSales,
            'closed_at' => now(),
            'difference'=> $totalSales - $data['closing_balance'],
            'cash_register_status_id' => 2 // 2 = Fechado
        ]);

        // // Calcular a diferença entre o saldo esperado e o real
        // $expectedBalance = $cashRegister->opening_balance + $totalSales;
        // $difference = $request->closing_balance - $expectedBalance;

        return response()->json([
            'message' => 'Caixa fechado com sucesso!',
            'cash_register' => $cashRegister,
            // 'total_sales' => $totalSales,
            // 'expected_balance' => $expectedBalance,
            // 'difference' => $difference
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
            $cashRegister = CashRegister::find($id);
        
            if (!$cashRegister) {
                return response()->json([
                    'message' => 'Nenhum caixa aberto encontrado para este usuário.'
                ], 404);
            }
        
            // Consultas agrupadas
            $orders = Order::with('itens') // Carrega os itens de cada pedido
                ->where('cash_register_id', $cashRegister->id)
                ->get();
        
            $orderItems = OrderItem::where('cash_register_id', $cashRegister->id)->get();
        
            $orderItemsTable = OrderItem::whereHas('order', function ($query) {
                $query->whereNotNull('table_id');
            })->where('cash_register_id', $cashRegister->id)->get();
        
            // $orderItems = $orders->flatMap->itens; // Coleta todos os itens em uma coleção única
        
            // Cálculos gerais
            $totalSales = $orderItems->sum('total');
            $totalOrders = $orderItems->count();
            $averageTicket = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
        
            // Separação de vendas rápidas e por mesa
            $tableOrders = $orders->whereNotNull('table_id');
            $quickSellOrders = $orders->whereNull('table_id');
        
            $totalOrderTables = $tableOrders->count();
            $totalOrderQuickSell = $quickSellOrders->count();
        
            $totalOrderTablesAmount = $orderItemsTable->sum('total');
            $totalOrderQuickSellAmount = $quickSellOrders->flatMap->itens->sum('total');
        
            $payments = Payment::where('cash_register_id',$cashRegister->id);
            $totalpayments = $payments->count();
            $totalpaymentsamount = $payments->sum('amount');
        
        
            return response()->json([
                'cash_register' => $cashRegister,
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'total_tables' => $totalOrderTables,
                'total_quick_sell' => $totalOrderQuickSell,
                'average_ticket' => round($averageTicket, 2),
                'total_tables_amount' => $totalOrderTablesAmount,
                'total_quick_sell_amount' => $totalOrderQuickSellAmount,
                'total_payments' => $totalpayments,
                'total_payments_amount' => $totalpaymentsamount,
        
            ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function dashboard()
    {
        $cashRegister = CashRegister::where('user_id', Auth::user()->id)
            ->where('cash_register_status_id', 1) // 1 = Aberto
            ->first();

        if (!$cashRegister) {
            return response()->json([
                'message' => 'aberto encontrado para este usuário.'
            ], 404);
        }

        // Consultas agrupadas
        $orders = Order::with('itens') // Carrega os itens de cada pedido
            ->where('cash_register_id', $cashRegister->id)
            ->get();

        $orderItems = OrderItem::where('cash_register_id', $cashRegister->id)->get();

        $orderItemsTable = OrderItem::whereHas('order', function ($query) {
            $query->whereNotNull('table_id');
        })->where('cash_register_id', $cashRegister->id)->get();

        // $orderItems = $orders->flatMap->itens; // Coleta todos os itens em uma coleção única

        // Cálculos gerais
        $totalSales = $orderItems->sum('total');
        $totalOrders = $orderItems->count();
        $averageTicket = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        // Separação de vendas rápidas e por mesa
        $tableOrders = $orders->whereNotNull('table_id');
        $quickSellOrders = $orders->whereNull('table_id');

        $totalOrderTables = $tableOrders->count();
        $totalOrderQuickSell = $quickSellOrders->count();

        $totalOrderTablesAmount = $orderItemsTable->sum('total');
        $totalOrderQuickSellAmount = $quickSellOrders->flatMap->itens->sum('total');

        $payments = Payment::where('cash_register_id',$cashRegister->id);
        $totalpayments = $payments->count();
        $totalpaymentsamount = $payments->sum('amount');


        return response()->json([
            'cash_register' => $cashRegister,
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'total_tables' => $totalOrderTables,
            'total_quick_sell' => $totalOrderQuickSell,
            'average_ticket' => round($averageTicket, 2),
            'total_tables_amount' => $totalOrderTablesAmount,
            'total_quick_sell_amount' => $totalOrderQuickSellAmount,
            'total_payments' => $totalpayments,
            'total_payments_amount' => $totalpaymentsamount,

        ]);
    }



public function paymentreport(){
    $userQuery = request('user');
    if($userQuery){
        $cashRegister = CashRegister::find($userQuery);
    }else{
        $cashRegister = CashRegister::where('user_id', Auth::user()->id)
        ->where('cash_register_status_id', 1) // 1 = Aberto
        ->first();
    }

    if (!$cashRegister) {
        return response()->json([
            'message' => 'Nenhum caixa aberto encontrado para este usuário.'
        ], 404);
    }
    $payments = Payment::with('method')->with('order.table')->where('cash_register_id',$cashRegister->id)->paginate(100);

    return response()->json($payments);
}

public function tablesellreport(){
    $userQuery = request('user');
    if($userQuery){
        $cashRegister = CashRegister::find($userQuery);
    }else{
        $cashRegister = CashRegister::where('user_id', Auth::user()->id)
        ->where('cash_register_status_id', 1) // 1 = Aberto
        ->first();
    }

    if (!$cashRegister) {
        return response()->json([
            'message' => 'Nenhum caixa aberto encontrado para este usuário.'
        ], 404);
    }
    $orderItemsTable = OrderItem::where('cash_register_id', $cashRegister->id)->get();

    $groupedOrderItems = $orderItemsTable->groupBy('order_id');

    // Obter os IDs dos pedidos únicos
    $orderIds = $groupedOrderItems->keys();

    // Buscar os pedidos relacionados
    $orders = Order::whereIn('id', $orderIds)->whereNotNull('table_id')
    ->with([
        'itens.product',    // Relacionamento com o produto de cada item
        'itens.status',     // Status de cada item
        'itens.user',       // Usuário responsável pelo item
        'table',            // Mesa associada ao pedido
        'status',           // Status do pedido
        'user'              // Usuário que fez o pedido
    ])
    ->paginate(100);

    return response()->json( $orders);
}

public function quicksellreport(){
    $userQuery = request('user');
    if($userQuery){
        $cashRegister = CashRegister::find($userQuery);
    }else{
        $cashRegister = CashRegister::where('user_id', Auth::user()->id)
        ->where('cash_register_status_id', 1) // 1 = Aberto
        ->first();
    }
    

    if (!$cashRegister) {
        return response()->json([
            'message' => 'Nenhum caixa aberto encontrado para este usuário.'
        ], 404);
    }
    $orders = Order::
    with([
        'itens.product',    // Relacionamento com o produto de cada item
        'itens.status',     // Status de cada item
        'itens.user',       // Usuário responsável pelo item
        'table',            // Mesa associada ao pedido
        'status',           // Status do pedido
        'user'              // Usuário que fez o pedido
    ])
        ->where('cash_register_id', $cashRegister->id)->whereNull('table_id')
        ->paginate(100);

    return response()->json($orders);
}

public function dailydashboard()
{
    $dateURL = request('date');

    $date = $dateURL ? date('Y-m-d', strtotime($dateURL)) : date('Y-m-d');

    $cashRegister = CashRegister::with('orderitens')
    ->with('user')
    ->with('status')->whereDate('created_at', $date)->get();

    $cashRegister->transform(function ($cash) {
        $cash->order_itens_total = $cash->orderitens->sum('total');
        return $cash;
    });

    $cashRegisterId = $cashRegister->pluck('id');

    $orders = Order::with('itens')
        ->whereIn('cash_register_id', $cashRegisterId)
        ->get();

    $orderItems = $orders->flatMap->itens;

    $orderItemsTable = $orderItems->filter(function ($item) {
        return $item->order->table_id !== null;
    });

    $totalSales = $orderItems->sum('total');
    $totalOrders = $orderItems->count();
    $averageTicket = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

    $tableOrders = $orders->whereNotNull('table_id');
    $quickSellOrders = $orders->whereNull('table_id');

    $totalOrderTables = $tableOrders->count();
    $totalOrderQuickSell = $quickSellOrders->count();

    $totalOrderTablesAmount = $orderItemsTable->sum('total');
    $totalOrderQuickSellAmount = $quickSellOrders->flatMap->itens->sum('total');

    $totalPayments = Payment::whereIn('cash_register_id', $cashRegisterId)->count();
    $totalPaymentsAmount = Payment::whereIn('cash_register_id', $cashRegisterId)->sum('amount');

    return response()->json([
        'cash_register' => $cashRegister,
        'total_sales' => $totalSales,
        'total_orders' => $totalOrders,
        'total_tables' => $totalOrderTables,
        'total_quick_sell' => $totalOrderQuickSell,
        'average_ticket' => round($averageTicket, 2),
        'total_tables_amount' => $totalOrderTablesAmount,
        'total_quick_sell_amount' => $totalOrderQuickSellAmount,
        'total_payments' => $totalPayments,
        'total_payments_amount' => $totalPaymentsAmount,
    ]);
}

public function paymentreportdaily(){
    $dateURL = request('date');

    $date = $dateURL ? date('Y-m-d', strtotime($dateURL)) : date('Y-m-d');

    $cashRegister = CashRegister::whereDate('created_at', $date)->get();
    $cashRegisterId = $cashRegister->pluck('id');


    $payments = Payment::with('method')->with('order.table')->whereIn('cash_register_id',$cashRegisterId)->paginate(100);

    return response()->json($payments);
}

public function tablesellreportdaily(){
    
    $dateURL = request('date');

    $date = $dateURL ? date('Y-m-d', strtotime($dateURL)) : date('Y-m-d');

    $cashRegister = CashRegister::whereDate('created_at', $date)->get();

    $cashRegisterId = $cashRegister->pluck('id');
    $orderItemsTable = OrderItem::whereIn('cash_register_id', $cashRegisterId)->get();

    $groupedOrderItems = $orderItemsTable->groupBy('order_id');

    // Obter os IDs dos pedidos únicos
    $orderIds = $groupedOrderItems->keys();

    // Buscar os pedidos relacionados
    $orders = Order::whereIn('id', $orderIds)->whereNotNull('table_id')
    ->with([
        'itens.product',    // Relacionamento com o produto de cada item
        'itens.status',     // Status de cada item
        'itens.user',       // Usuário responsável pelo item
        'table',            // Mesa associada ao pedido
        'status',           // Status do pedido
        'user'              // Usuário que fez o pedido
    ])
    ->paginate(100);

    return response()->json( $orders);
}

public function quicksellreportdaily(){
    $dateURL = request('date');

    $date = $dateURL ? date('Y-m-d', strtotime($dateURL)) : date('Y-m-d');

    $cashRegister = CashRegister::whereDate('created_at', $date)->get();

    $cashRegisterId = $cashRegister->pluck('id');
    $orders = Order::
    with([
        'itens.product',    // Relacionamento com o produto de cada item
        'itens.status',     // Status de cada item
        'itens.user',       // Usuário responsável pelo item
        'table',            // Mesa associada ao pedido
        'status',           // Status do pedido
        'user'              // Usuário que fez o pedido
    ])
        ->whereIn('cash_register_id', $cashRegisterId)->whereNull('table_id')
        ->paginate(100);

    return response()->json($orders);
}


public function report(){

    $dateURL = request('date');

    $date = $dateURL ? date('Y-m-d', strtotime($dateURL)) : date('Y-m-d');

    $cashRegister = CashRegister::with('orderitens')
    ->with('user')
    ->with('status')->whereDate('created_at', $date)->get();

    $cashRegister->transform(function ($cash) {
        $cash->order_itens_total = $cash->orderitens->sum('total');
        return $cash;
    });

    $cashRegisterId = $cashRegister->pluck('id');

    $orders = Order::with('itens')
        ->whereIn('cash_register_id', $cashRegisterId)
        ->get();

    $orderItems = $orders->flatMap->itens;

    $orderItemsTable = $orderItems->filter(function ($item) {
        return $item->order->table_id !== null;
    });

    $totalSales = $orderItems->sum('total');
    $totalOrders = $orderItems->count();
    $averageTicket = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

    $tableOrders = $orders->whereNotNull('table_id');
    $quickSellOrders = $orders->whereNull('table_id');

    $totalOrderTables = $tableOrders->count();
    $totalOrderQuickSell = $quickSellOrders->count();

    $totalOrderTablesAmount = $orderItemsTable->sum('total');
    $totalOrderQuickSellAmount = $quickSellOrders->flatMap->itens->sum('total');

    $totalPayments = Payment::whereIn('cash_register_id', $cashRegisterId)->count();
    $totalPaymentsAmount = Payment::whereIn('cash_register_id', $cashRegisterId)->sum('amount');

    $ticket = round($averageTicket, 2);
    
    
    $paymentsReport = Payment::with('method')->with('order.table')->whereIn('cash_register_id',$cashRegisterId)->get();
    $orderItemsTableReport = OrderItem::whereIn('cash_register_id', $cashRegisterId)->get();
    $groupedOrderItems = $orderItemsTableReport->groupBy('order_id');
    $orderIds = $groupedOrderItems->keys();
    $ordersReport = Order::whereIn('id', $orderIds)->whereNotNull('table_id')->with(['itens.product','itens.status','itens.user','table','status','user'])->get();
    $quickOrderReport = Order::with(['itens.product','itens.status','itens.user','table','status','user'])->whereIn('cash_register_id', $cashRegisterId)->whereNull('table_id')->get();

    // $orderItemsTableReportGrouped = OrderItem::whereIn('cash_register_id', $cashRegisterId)
    // ->join('products', 'order_items.product_id', '=', 'products.id')
    // ->select('products.name as product_name', DB::raw('SUM(order_items.price * order_items.quantity) as total_amount'), DB::raw('SUM(order_items.quantity) as total_quantity'))
    // ->groupBy('products.name')
    // ->get();

    $pdf = Pdf::loadView('pdf.report', compact('cashRegister','totalSales','totalOrders','totalOrderTables','totalOrderQuickSell','ticket','totalOrderTablesAmount','totalOrderQuickSellAmount','totalPayments','totalPaymentsAmount','paymentsReport','ordersReport','quickOrderReport'))->setOptions([
        'setPaper'=>'a8',
        // 'setPaper' => [0, 0, 640, 2376],
        'defaultFont' => 'sans-serif',
        'isRemoteEnabled' => 'true'
    ]);
    return $pdf->setPaper('a4')->stream('report.pdf');
}


public function reportstock(){

    $dateURL = request('date');

    $date = $dateURL ? date('Y-m-d', strtotime($dateURL)) : date('Y-m-d');

    $cashRegister = CashRegister::with('orderitens')
    ->with('user')
    ->with('status')->whereDate('created_at', $date)->get();

    $cashRegister->transform(function ($cash) {
        $cash->order_itens_total = $cash->orderitens->sum('total');
        return $cash;
    });

    $cashRegisterId = $cashRegister->pluck('id');


    
    $orderItemsTableReportKitchen = OrderItem::whereIn('cash_register_id', $cashRegisterId)
    ->whereHas('product', function ($query) {
        $query->where('department_id', 1);
    })
    ->select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(total) as total_value'))
    ->groupBy('product_id')
    ->with(['product' => function ($query) {
        $query->withQuantityInPrincipalStock(); // Adiciona a quantidade do estoque principal
    }])
    ->get();

    // Adicionar quantidade inicial do daily_stock_snapshots para cada produto da cozinha
    foreach ($orderItemsTableReportKitchen as $item) {
        $dailySnapshot = \App\Models\DailyStockSnapshot::where('product_id', $item->product_id)
            ->where('date', $date)
            ->first();
        $item->initial_stock_quantity = $dailySnapshot ? $dailySnapshot->quantity : 0;
        $item->initial_created = $dailySnapshot ? $dailySnapshot->created_at : null;
    }

    $orderItemsTableReportBar = OrderItem::whereIn('cash_register_id', $cashRegisterId)
    ->whereHas('product', function ($query) {
        $query->where('department_id', 2);
    })
    ->select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(total) as total_value'))
    ->groupBy('product_id')
    ->with(['product' => function ($query) {
        $query->withQuantityInPrincipalStock(); // Adiciona a quantidade do estoque principal
    }])
    ->get();

    // Adicionar quantidade inicial do daily_stock_snapshots para cada produto do bar
    foreach ($orderItemsTableReportBar as $item) {
        $dailySnapshot = \App\Models\DailyStockSnapshot::where('product_id', $item->product_id)
            ->where('date', $date)
            ->first();
        $item->initial_stock_quantity = $dailySnapshot ? $dailySnapshot->quantity : 0;
        $item->initial_created = $dailySnapshot ? $dailySnapshot->created_at : null;
    }

    $pdf = Pdf::loadView('pdf.reportstock', compact('orderItemsTableReportKitchen','orderItemsTableReportBar'))->setOptions([
        'setPaper'=>'a4',
        // 'setPaper' => [0, 0, 640, 2376],
        'defaultFont' => 'sans-serif',
        'isRemoteEnabled' => 'true'
    ]);
    return $pdf->setPaper('a4')->stream('report.pdf');
}


public function reporttrash(){

    $dateURL = request('date');

    $date = $dateURL ? date('Y-m-d', strtotime($dateURL)) : date('Y-m-d');

    $cashRegister = CashRegister::with('orderitens')
    ->with('user')
    ->with('status')->whereDate('created_at', $date)->get();

    $cashRegister->transform(function ($cash) {
        $cash->order_itens_total = $cash->orderitens->sum('total');
        return $cash;
    });

    $cashRegisterId = $cashRegister->pluck('id');

    $orderItems = OrderItem::onlyTrashed()->whereIn('cash_register_id', $cashRegisterId)->get();

    
    $totalSales = $orderItems->sum('total');
    


    


    $pdf = Pdf::loadView('pdf.reporttrash', compact('cashRegister','totalSales','orderItems'))->setOptions([
        'setPaper'=>'a8',
        // 'setPaper' => [0, 0, 640, 2376],
        'defaultFont' => 'sans-serif',
        'isRemoteEnabled' => 'true'
    ]);
    return $pdf->setPaper('a4')->stream('reporttrash.pdf');
}

}
