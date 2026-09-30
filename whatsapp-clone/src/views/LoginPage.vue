<!-- C:\xampp\htdocs\new app\whatsapp-clone\src\views\LoginPage.vue -->

<template>
  <ion-page class="login-page">
    <ion-header class="ion-no-border header-transparent">
      <ion-toolbar class="toolbar-transparent">
        <ion-title class="header-title">Acceso</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="login-content" :fullscreen="true">
      <!-- Luces ambientales de fondo (Aurora Glow) -->
      <div class="ambient-glow glow-1"></div>
      <div class="ambient-glow glow-2"></div>

      <div class="login-wrapper">
        <div class="login-card">

          <!-- Logo Orbe Futurista -->
          <div class="logo-wrapper">
            <div class="logo-orb">
              <ion-icon :icon="logInOutline" />
            </div>
          </div>

          <!-- Título y Subtítulo -->
          <div class="header-text">
            <h1 class="welcome-title">Bienvenido</h1>
            <p class="welcome-subtitle">Inicia sesión para ingresar a la red</p>
          </div>

          <!-- Formulario -->
          <div class="form-container">
            <div class="input-group">
              <ion-item lines="none" class="custom-input-item">
                <ion-input
                  v-model="telefono"
                  label="Teléfono"
                  label-placement="floating"
                  type="tel"
                  class="custom-input"
                />
              </ion-item>
            </div>

            <div class="input-group">
              <ion-item lines="none" class="custom-input-item">
                <ion-input
                  v-model="password"
                  label="Contraseña"
                  label-placement="floating"
                  type="password"
                  class="custom-input"
                />
              </ion-item>
            </div>

            <!-- Botones -->
            <ion-button
              expand="block"
              class="btn-primary-glow"
              @click="iniciarSesion"
            >
              Iniciar sesión
            </ion-button>

            <ion-button
              expand="block"
              fill="clear"
              class="btn-secondary-glass"
              @click="irRegistro"
            >
              ¿No tienes cuenta? <span class="highlight">Regístrate</span>
            </ion-button>
          </div>

        </div>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonItem,
  IonInput,
  IonButton,
  IonIcon
} from '@ionic/vue';
import { logInOutline } from 'ionicons/icons';

const router = useRouter();

const telefono = ref('');
const password = ref('');

const iniciarSesion = async () => {
  if (!telefono.value || !password.value) {
    alert('Completa todos los campos');
    return;
  }

  try {
    const datos = new URLSearchParams();
    datos.append('telefono', telefono.value);
    datos.append('password', password.value);

    const respuesta = await fetch(
      'http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=login',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: datos.toString()
      }
    );

    const texto = await respuesta.text();
    console.log('RESPUESTA PHP:', texto);

    const resultado = JSON.parse(texto);
    console.log('RESULTADO:', resultado);

    if (resultado.ok) {
      localStorage.setItem('token', resultado.token);
      localStorage.setItem('usuario', JSON.stringify(resultado.usuario));
      alert('Bienvenido ' + resultado.usuario.nombre);
      router.push('/chats');
    } else {
      alert(resultado.error);
    }
  } catch (error) {
    console.error('ERROR COMPLETO:', error);
    alert('Error al iniciar sesión');
  }
};

const irRegistro = () => {
  router.push('/registro');
};
</script>

<style scoped>
/* ===== PÁGINA Y FONDO GENERAL ===== */
.login-page {
  --background: #090d16;
  background-color: #090d16;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.login-content {
  --background: #090d16;
  position: relative;
  overflow: hidden;
}

.header-transparent {
  background: transparent;
}

.toolbar-transparent {
  --background: transparent;
  --border-width: 0;
}

.header-title {
  color: #64748b;
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
}

/* ===== RESPLANDORES AMBIENTALES DE FONDO (AURORA) ===== */
.ambient-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
  opacity: 0.5;
}

.glow-1 {
  width: 260px;
  height: 260px;
  background: #6366f1;
  top: -40px;
  left: -40px;
}

.glow-2 {
  width: 300px;
  height: 300px;
  background: #06b6d4;
  bottom: -60px;
  right: -60px;
}

/* ===== CONTENEDOR PRINCIPAL Y TARJETA GLASS ===== */
.login-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100%;
  padding: 20px 16px;
  position: relative;
  z-index: 1;
}

.login-card {
  width: 100%;
  max-width: 410px;
  background: rgba(18, 24, 38, 0.65);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 28px;
  padding: 36px 24px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
}

/* ===== LOGO ORBE ===== */
.logo-wrapper {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}

.logo-orb {
  width: 76px;
  height: 76px;
  border-radius: 24px;
  background: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
  transform: rotate(-5deg);
  transition: transform 0.3s ease;
}

.logo-orb ion-icon {
  font-size: 38px;
  color: #ffffff;
  transform: rotate(5deg);
}

/* ===== TEXTOS DE BIENVENIDA ===== */
.header-text {
  text-align: center;
  margin-bottom: 28px;
}

.welcome-title {
  font-size: 28px;
  font-weight: 800;
  margin: 0 0 6px 0;
  background: linear-gradient(135deg, #ffffff 30%, #94a3b8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  letter-spacing: -0.5px;
}

.welcome-subtitle {
  font-size: 14px;
  color: #8a99ad;
  margin: 0;
}

/* ===== ENTRADAS DE TEXTO (INPUTS) ===== */
.form-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.input-group {
  position: relative;
}

.custom-input-item {
  --background: rgba(255, 255, 255, 0.04);
  --border-radius: 16px;
  --padding-start: 16px;
  --highlight-color-focused: #06b6d4;
  border: 1px solid rgba(255, 255, 255, 0.06);
  transition: all 0.2s ease;
}

.custom-input-item:focus-within {
  border-color: rgba(6, 182, 212, 0.5);
  box-shadow: 0 0 15px rgba(6, 182, 212, 0.15);
}

.custom-input {
  --color: #ffffff;
  --placeholder-color: #64748b;
  --highlight-color-focused: #06b6d4;
  color: #ffffff;
}

/* ===== BOTONES ===== */
.btn-primary-glow {
  margin-top: 10px;
  --background: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
  --color: #ffffff;
  --border-radius: 16px;
  --box-shadow: 0 8px 25px rgba(99, 102, 241, 0.35);
  font-weight: 700;
  font-size: 16px;
  height: 52px;
  letter-spacing: 0.3px;
  text-transform: none;
}

.btn-secondary-glass {
  margin-top: 4px;
  --color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  text-transform: none;
}

.highlight {
  color: #38bdf8;
  font-weight: 700;
  margin-left: 4px;
}
</style>