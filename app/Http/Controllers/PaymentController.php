<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use abdulmueid\mpesa\Transaction;
use abdulmueid\mpesa\Config;

class PaymentController extends Controller
{
    public function processPayment(Request $request): JsonResponse
    {
        try {
            // Validar dados de entrada
            $request->validate([
                'amount' => 'required|numeric|min:0',
                'phone' => 'required|string|min:9',
                'description' => 'required|string|max:255',
                'reference' => 'required|string|max:100'
            ]);

            $amount = $request->amount;
            $phone = $this->formatPhoneNumber($request->phone);
            $description = $request->description;
            $reference = $request->reference;

            Log::info('Processing payment', [
                'amount' => $amount,
                'phone' => $phone,
                'description' => $description,
                'reference' => $reference
            ]);

            // Por enquanto, simular processamento de pagamento
            // Aqui você integraria com a API da carteira móvel real
            $paymentResult = $this->processWalletPayment($amount, $phone, $description, $reference);

            if ($paymentResult['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pagamento processado com sucesso',
                    'reference' => $paymentResult['reference'],
                    'transaction_id' => $paymentResult['transaction_id'],
                    'amount' => $amount,
                    'phone' => $phone
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Payment processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno no processamento do pagamento'
            ], 500);
        }
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Remove todos os espaços e caracteres especiais
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Se começar com +258, manter
        if (str_starts_with($phone, '+258')) {
            return $phone;
        }
        
        // Se começar com 258, adicionar +
        if (str_starts_with($phone, '258')) {
            return '+' . $phone;
        }
        
        // Se começar com 8 ou 9 (números moçambicanos), adicionar +258
        if (str_starts_with($phone, '8') || str_starts_with($phone, '9')) {
            return '+258' . $phone;
        }
        
        return $phone;
    }

    private function processWalletPayment(float $amount, string $phone, string $description, string $reference): array
    {
        try {
            // Integração com M-Pesa usando abdulmueid/mpesa
            $config = \abdulmueid\mpesa\Config::loadFromFile('config.php');
            $transactionmpesa = new \abdulmueid\mpesa\Transaction($config);
            
            // Remover o prefixo +258 se existir, pois a API espera apenas números
            $msisdn = str_replace('+258', '', $phone);
            
            // Usar a referência fornecida
            $ref = $reference;
            
            Log::info('M-Pesa payment request', [
                'amount' => $amount,
                'msisdn' => $msisdn,
                'reference' => $ref,
                'description' => $description
            ]);
            
            $c2b = $transactionmpesa->c2b(
                $amount, // valor a cobrar do cliente
                $msisdn, // número de telefone do cliente vodacom com mpesa registrado
                $ref, // referencia do pagamento
                $ref // referencia do pagamento
            );
            
            Log::info('M-Pesa response', [
                'code' => $c2b->getCode(),
                'response' => $c2b
            ]);
            
            if($c2b->getCode() === 'INS-0') { // codigo de sucesso de pagamento
                return [
                    'success' => true,
                    'reference' => $ref,
                    'transaction_id' => 'MPESA_' . $ref,
                    'amount' => $amount,
                    'phone' => $phone,
                    'mpesa_code' => $c2b->getCode(),
                    'message' => 'Pagamento M-Pesa processado com sucesso'
                ];
            }
            
            // Tratar diferentes códigos de erro do M-Pesa
            $errorMessages = [
                'INS-1' => 'Erro interno, volte a tentar novamente',
                'INS-2' => 'API inválida, volte a tentar novamente',
                'INS-4' => 'Usuário não ativo',
                'INS-5' => 'Transação cancelada pelo usuário',
                'INS-6' => 'Transação falhou',
                'INS-9' => 'O tempo expirou. Volte a tentar',
                'INS-10' => 'Transação duplicada',
                'INS-16' => 'Erro interno, volte mais tarde',
                'INS-2006' => 'Saldo insuficiente',
                'INS-2051' => 'Número de telefone inválido'
            ];
            
            $errorMessage = $errorMessages[$c2b->getCode()] ?? 'Erro desconhecido no pagamento';
            
            Log::error('M-Pesa payment failed', [
                'code' => $c2b->getCode(),
                'message' => $errorMessage,
                'reference' => $ref
            ]);
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'mpesa_code' => $c2b->getCode()
            ];
            
        } catch (\Exception $e) {
            Log::error('M-Pesa API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Erro de comunicação com M-Pesa: ' . $e->getMessage()
            ];
        }
    }
    

}
