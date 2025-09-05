<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

// Reactive data
const participant = ref(null)
const loading = ref(true)
const error = ref(null)
const submitting = ref(false)
const submitError = ref(null)
const submitSuccess = ref(false)

// Page content (static)
const backgroundImage = '/votaaqui/assets/img/events/showcase-9.webp'
const defaultImage = '/votaaqui/assets/img/events/speaker-4.webp'

// Vote form
const voteForm = ref({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  acceptTerms: false,
  newsletter: false
})

// Methods
const fetchParticipant = async () => {
  try {
    loading.value = true
    error.value = null
    
    const participantId = route.params.id
    if (!participantId) {
      throw new Error('ID do participante não fornecido')
    }

    const response = await axios.get(`/api/participants/${participantId}`)
    participant.value = response.data.data || response.data
    
  } catch (err) {
    console.error('Erro ao carregar participante:', err)
    error.value = 'Não foi possível carregar as informações do participante. Verifique se o link está correto.'
  } finally {
    loading.value = false
  }
}

const submitVote = async () => {
  try {
    submitting.value = true
    submitError.value = null
    submitSuccess.value = false
    
    const voteData = {
      participant_id: participant.value.id,
      voter_name: `${voteForm.value.firstName} ${voteForm.value.lastName}`,
      voter_email: voteForm.value.email || null,
      voter_phone: voteForm.value.phone || null,
      newsletter_subscription: voteForm.value.newsletter
    }
    
    await axios.post('/api/votes', voteData)
    
    submitSuccess.value = true
    
    // Reset form after successful submission
    setTimeout(() => {
      router.push('/')
    }, 3000)
    
  } catch (err) {
    console.error('Erro ao enviar voto:', err)
    
    if (err.response?.status === 429) {
      submitError.value = 'Limite de votos excedido. Tente novamente mais tarde.'
    } else if (err.response?.data?.message) {
      submitError.value = err.response.data.message
    } else {
      submitError.value = 'Erro ao registrar voto. Tente novamente.'
    }
  } finally {
    submitting.value = false
  }
}

const handleImageError = (event) => {
  event.target.src = defaultImage
}

// Lifecycle
onMounted(() => {
  fetchParticipant()
})
</script>

<template>
  <div>
    <!-- Header -->
    <header id="header" class="header d-flex align-items-center fixed-top">
      <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
        <router-link to="/" class="logo d-flex align-items-center">
          <h1 class="sitename">Votaaqui</h1>
        </router-link>
        
        <!-- Navigation Menu -->
        <nav class="d-none d-md-flex">
          <ul class="nav-links d-flex list-unstyled mb-0">
            <li class="me-3">
              <router-link to="/" class="nav-link">
                <i class="bi bi-arrow-left me-1"></i>
                Voltar
              </router-link>
            </li>
          </ul>
        </nav>
      </div>
    </header>

    <main class="main">
      <!-- Page Title -->
      <div class="page-title dark-background" style="background-image: url('/votaaqui/assets/img/events/showcase-9.webp')">
        <div class="container position-relative">
          <h1>Vote no seu participante</h1>
          <p>Complete os dados para confirmar o seu voto.</p>
          <nav class="breadcrumbs">
            <ol>
              <li><router-link to="/">Home</router-link></li>
              <li class="current">Seu voto</li>
            </ol>
          </nav>
        </div>
      </div>

      <!-- Vote Form Section -->
      <section id="vote-form" class="buy-tickets section">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-8">
              <div class="ticket-form-wrapper">
                
                <!-- Loading State -->
                <div v-if="loading" class="text-center my-5">
                  <div class="spinner-border" role="status">
                    <span class="visually-hidden">Carregando...</span>
                  </div>
                  <p class="mt-3">Carregando informações do participante...</p>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="alert alert-danger" role="alert">
                  <h5>Erro ao carregar participante</h5>
                  <p>{{ error }}</p>
                  <router-link to="/" class="btn btn-outline-danger">
                    <i class="bi bi-arrow-left me-1"></i>
                    Voltar à página inicial
                  </router-link>
                </div>

                <!-- Main Content -->
                <div v-else-if="participant">
                  <!-- Event Info -->
                  <div class="event-info mb-5">
                    <h3>Reality Show - Estrelas do LIV</h3>
                    <div class="event-details">
                      <div class="event-meta">
                        <span><i class="bi bi-calendar-event"></i> Setembro 2025</span>
                        <span><i class="bi bi-geo-alt"></i> Moçambique</span>
                        <span><i class="bi bi-clock"></i> Votação 24h</span>
                      </div>
                    </div>
                  </div>

                  <!-- Participant Info -->
                  <div class="ticket-types">
                    <h4>Participante selecionado</h4>
                    
                    <div class="ticket-option selected">
                      <div class="participant-card">
                        <div class="participant-image">
                          <img 
                            :src="participant.photo_url || '/votaaqui/assets/img/events/speaker-4.webp'" 
                            :alt="participant.name"
                            class="img-fluid"
                            @error="handleImageError"
                          >
                        </div>
                        <div class="participant-info">
                          <h5>{{ participant.stage_name || participant.name }}</h5>
                          <p class="participant-bio">{{ participant.biography || 'Participante do Reality Show' }}</p>
                          <div class="participant-details">
                            <span v-if="participant.age">
                              <i class="bi bi-person"></i> {{ participant.age }} anos
                            </span>
                            <span v-if="participant.city">
                              <i class="bi bi-geo-alt"></i> {{ participant.city }}
                            </span>
                            <span v-if="participant.skills">
                              <i class="bi bi-star"></i> {{ participant.skills }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Vote Form -->
                  <form @submit.prevent="submitVote" class="php-email-form ticket-form">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="first_name">Nome *</label>
                          <input 
                            type="text" 
                            v-model="voteForm.firstName"
                            id="first_name" 
                            class="form-control" 
                            required
                            :disabled="submitting"
                          >
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="last_name">Apelido *</label>
                          <input 
                            type="text" 
                            v-model="voteForm.lastName"
                            id="last_name" 
                            class="form-control" 
                            required
                            :disabled="submitting"
                          >
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="email">Email</label>
                          <input 
                            type="email" 
                            v-model="voteForm.email"
                            id="email" 
                            class="form-control"
                            :disabled="submitting"
                          >
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="phone">Contacto</label>
                          <input 
                            type="tel" 
                            v-model="voteForm.phone"
                            id="phone" 
                            class="form-control"
                            :disabled="submitting"
                          >
                        </div>
                      </div>
                    </div>

                    <!-- Vote Summary -->
                    <div class="pricing-summary">
                      <h5>Resumo do Voto</h5>
                      <div class="summary-row">
                        <span>Voto em:</span>
                        <span class="ticket-name-display">{{ participant.stage_name || participant.name }}</span>
                      </div>
                      <div class="summary-row">
                        <span>Código:</span>
                        <span class="quantity-display">{{ participant.voting_code }}</span>
                      </div>
                      <div class="summary-row">
                        <span>Tipo de voto:</span>
                        <span class="unit-price-display">Gratuito</span>
                      </div>
                      <div class="summary-row total">
                        <span>Total:</span>
                        <span class="total-price">Grátis</span>
                      </div>
                      <div class="tax-note">*Votação gratuita e ilimitada</div>
                    </div>

                    <!-- Terms -->
                    <div class="terms-checkbox">
                      <input 
                        type="checkbox" 
                        id="terms" 
                        v-model="voteForm.acceptTerms"
                        required
                        :disabled="submitting"
                      >
                      <label for="terms">
                        Concordo com os <a href="#" target="_blank">Termos e Condições</a> 
                        e <a href="#" target="_blank">Política de Privacidade</a> *
                      </label>
                    </div>

                    <div class="newsletter-checkbox">
                      <input 
                        type="checkbox" 
                        id="newsletter" 
                        v-model="voteForm.newsletter"
                        :disabled="submitting"
                      >
                      <label for="newsletter">
                        Subscrever newsletter para atualizações do evento
                      </label>
                    </div>

                    <!-- Status Messages -->
                    <div v-if="submitting" class="loading">Enviando voto...</div>
                    <div v-if="submitError" class="error-message">{{ submitError }}</div>
                    <div v-if="submitSuccess" class="sent-message">
                      Seu voto foi registrado com sucesso! Obrigado por participar.
                    </div>

                    <!-- Submit Button -->
                    <div class="form-submit">
                      <button 
                        type="submit" 
                        class="btn-submit"
                        :disabled="submitting || !voteForm.acceptTerms"
                      >
                        <i class="bi bi-heart me-2"></i>
                        {{ submitting ? 'Enviando...' : 'Confirmar Voto' }}
                      </button>
                    </div>
                  </form>

                  <!-- Security Info -->
                  <div class="security-info">
                    <div class="security-badges">
                      <div class="badge-item">
                        <i class="bi bi-shield-check"></i>
                        <span>Voto Seguro</span>
                      </div>
                      <div class="badge-item">
                        <i class="bi bi-lock"></i>
                        <span>Dados Protegidos</span>
                      </div>
                      <div class="badge-item">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Totalmente Gratuito</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer id="footer" class="footer position-relative dark-background">
      <div class="container footer-top">
        <div class="row gy-4">
          <div class="col-lg-4 col-md-6 footer-about">
            <router-link to="/" class="logo d-flex align-items-center">
              <span class="sitename">VotaAqui</span>
            </router-link>
            <div class="footer-contact pt-3">
              <p>Rua Principal, 123</p>
              <p>Beira, Sofala, Moçambique</p>
              <p class="mt-3"><strong>Telefone:</strong> <span>+258 123 456 789</span></p>
              <p><strong>Email:</strong> <span>info@votaaqui.com</span></p>
            </div>
          </div>
          
          <div class="col-lg-2 col-md-3 footer-links">
            <h4>Links Úteis</h4>
            <ul>
              <li><router-link to="/">Início</router-link></li>
              <li><a href="#" @click.prevent>Sobre nós</a></li>
              <li><a href="#" @click.prevent>Termos de serviço</a></li>
              <li><a href="#" @click.prevent>Política de privacidade</a></li>
            </ul>
          </div>
        </div>
      </div>

      <div class="container copyright text-center mt-4">
        <p>© <span>Copyright</span> <strong class="px-1 sitename">VotaAqui</strong> <span>Todos os direitos reservados</span></p>
        <div class="credits">
          Desenvolvido com ❤️ para o Reality Show Estrelas do LIV
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* Component-specific styles */
.header {
  background-color: rgba(0, 0, 0, 0.9);
  padding: 1rem 0;
}

.nav-links {
  align-items: center;
}

.nav-link {
  color: #bdc3c7;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.3s ease;
  padding: 0.5rem 1rem;
  border-radius: 5px;
}

.nav-link:hover {
  color: #667eea;
  background-color: rgba(102, 126, 234, 0.1);
}

.page-title {
  padding: 120px 0 60px 0;
  background-size: cover;
  background-position: center;
  color: white;
  position: relative;
}

.page-title::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
}

.page-title .container {
  position: relative;
  z-index: 1;
  text-align: center;
}

.breadcrumbs ol {
  list-style: none;
  padding: 0;
  margin: 1rem 0 0;
  display: flex;
  justify-content: center;
  gap: 0.5rem;
}

.breadcrumbs li {
  color: #bdc3c7;
}

.breadcrumbs li a {
  color: #667eea;
  text-decoration: none;
}

.breadcrumbs li.current {
  color: white;
}

.breadcrumbs li:not(:last-child)::after {
  content: '/';
  margin-left: 0.5rem;
  color: #bdc3c7;
}

.participant-card {
  display: flex;
  gap: 1.5rem;
  padding: 1.5rem;
  background: white;
  border-radius: 10px;
  box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.participant-image {
  flex-shrink: 0;
  width: 150px;
  height: 150px;
  border-radius: 10px;
  overflow: hidden;
}

.participant-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.participant-info h5 {
  margin: 0 0 0.5rem;
  color: #2c3e50;
  font-size: 1.5rem;
}

.participant-bio {
  color: #666;
  margin-bottom: 1rem;
  line-height: 1.5;
}

.participant-details {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.participant-details span {
  color: #667eea;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.ticket-option.selected {
  border: 2px solid #667eea;
  border-radius: 10px;
  margin-bottom: 2rem;
}

.btn-submit {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  padding: 1rem 2rem;
  border-radius: 25px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-weight: 500;
  font-size: 1.1rem;
  width: 100%;
}

.btn-submit:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.footer {
  background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
  color: white;
  padding: 3rem 0 1rem;
  margin-top: 3rem;
}

.spinner-border {
  color: #667eea;
  width: 3rem;
  height: 3rem;
}

.alert-danger {
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
  border: none;
  color: white;
  border-radius: 10px;
}

/* Form styles */
.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  font-weight: 500;
  color: #2c3e50;
  margin-bottom: 0.5rem;
  display: block;
}

.form-control {
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  padding: 0.75rem;
  transition: border-color 0.3s ease;
}

.form-control:focus {
  border-color: #667eea;
  box-shadow: none;
}

.pricing-summary {
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 10px;
  margin: 2rem 0;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.summary-row.total {
  font-weight: 600;
  font-size: 1.1rem;
  padding-top: 0.5rem;
  border-top: 1px solid #dee2e6;
  margin-top: 1rem;
}

.terms-checkbox,
.newsletter-checkbox {
  margin-bottom: 1rem;
}

.terms-checkbox input,
.newsletter-checkbox input {
  margin-right: 0.5rem;
}

.security-info {
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 10px;
  margin-top: 2rem;
}

.security-badges {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  justify-content: center;
}

.badge-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #667eea;
  font-weight: 500;
}

.loading,
.error-message,
.sent-message {
  text-align: center;
  padding: 1rem;
  border-radius: 8px;
  margin: 1rem 0;
}

.loading {
  background: #e3f2fd;
  color: #1976d2;
}

.error-message {
  background: #ffebee;
  color: #c62828;
}

.sent-message {
  background: #e8f5e8;
  color: #2e7d32;
}

/* Responsive */
@media (max-width: 768px) {
  .participant-card {
    flex-direction: column;
    text-align: center;
  }
  
  .participant-image {
    width: 120px;
    height: 120px;
    margin: 0 auto;
  }
  
  .participant-details {
    justify-content: center;
  }
  
  .security-badges {
    flex-direction: column;
    align-items: center;
  }
}
</style>
