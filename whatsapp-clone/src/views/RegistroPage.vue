<!-- C:\xampp\htdocs\new app\whatsapp-clone\src\views\RegistroPage.vue -->

<template>
  <ion-page class="registro-page">
    <ion-header class="ion-no-border header-transparent">
      <ion-toolbar class="toolbar-transparent">
        <ion-title class="header-title">Registro</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="registro-content" :fullscreen="true">
      <!-- Luces ambientales de fondo (Aurora Glow) -->
      <div class="ambient-glow glow-1"></div>
      <div class="ambient-glow glow-2"></div>

      <div class="registro-wrapper">
        <div class="registro-card">

          <!-- Logo Orbe Futurista -->
          <div class="logo-wrapper">
            <div class="logo-orb">
              <ion-icon :icon="personAddOutline" />
            </div>
          </div>

          <!-- Título y Subtítulo -->
          <div class="header-text">
            <h1 class="welcome-title">Crear cuenta</h1>
            <p class="welcome-subtitle">Registra tus datos para comenzar</p>
          </div>

          <!-- Formulario -->
          <div class="form-container">
            <div class="input-row">
              <ion-item lines="none" class="custom-input-item">
                <ion-input
                  v-model="nombre"
                  label="Nombre"
                  label-placement="floating"
                  type="text"
                  class="custom-input"
                />
              </ion-item>

              <ion-item lines="none" class="custom-input-item">
                <ion-input
                  v-model="apellido"
                  label="Apellido"
                  label-placement="floating"
                  type="text"
                  class="custom-input"
                />
              </ion-item>
            </div>

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
              @click="registrarUsuario"
            >
              Crear cuenta
            </ion-button>

            <ion-button
              expand="block"
              fill="clear"
              class="btn-secondary-glass"
              @click="irLogin"
            >
              ¿Ya tienes cuenta? <span class="highlight">Inicia sesión</span>
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
import { personAddOutline } from 'ionicons/icons';

const router = useRouter();

const nombre = ref('');
const apellido = ref('');
const telefono = ref('');
const password = ref('');

const registrarUsuario = async () => {
  if (!nombre.value || !apellido.value || !telefono.value || !password.value) {
    alert('Completa todos los campos');
    return;
  }

  try {
    const datos = new URLSearchParams();
    datos.append('nombre', nombre.value);
    datos.append('apellido', apellido.value);
    datos.append('telefono', telefono.value);
    datos.append('password', password.value);

    const respuesta = await fetch(
      'https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/usuarios_controlador.php?task=insertUsuario',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: datos.toString()
      }
    );

    const resultado = await respuesta.json();
    console.log(resultado);

    if (resultado.ok) {
      alert('Usuario registrado correctamente. ID: ' + resultado.id);
      nombre.value = '';
      apellido.value = '';
      telefono.value = '';
      password.value = '';
      router.push('/login');
    } else {
      alert(resultado.error);
    }
  } catch (error) {
    console.error('Error al registrar usuario:', error);
    alert('Error al registrar usuario');
  }
};

const irLogin = () => {
  router.push('/login');
};
</script>

<style scoped>
/* ===== ESTILO GLOBAL Y FONDO ===== */
.registro-page {
  --background: #090d16;
  background-color: #090d16;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.registro-content {
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

/* ===== RESPLANDORES AMBIENTALES ===== */
.ambient-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(85px);
  pointer-events: none;
  z-index: 0;
  opacity: 0.45;
}

.glow-1 {
  width: 280px;
  height: 280px;
  background: var(--app-accent, #10b981);
  top: -50px;
  right: -50px;
}

.glow-2 {
  width: 280px;
  height: 280px;
  background: var(--app-accent-secondary, #06b6d4);
  bottom: -50px;
  left: -50px;
}

/* ===== TARJETA GLASSMARPHISM ===== */
.registro-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100%;
  padding: 16px;
  position: relative;
  z-index: 1;
}

.registro-card {
  width: 100%;
  max-width: 420px;
  background: rgba(18, 24, 38, 0.65);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 28px;
  padding: 32px 22px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
}

/* ===== ORBE DEL LOGO ===== */
.logo-wrapper {
  display: flex;
  justify-content: center;
  margin-bottom: 18px;
}

.logo-orb {
  width: 74px;
  height: 74px;
  border-radius: 22px;
  background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10px 25px rgba(16, 185, 129, 0.35);
  transform: rotate(5deg);
}

.logo-orb ion-icon {
  font-size: 36px;
  color: #ffffff;
  transform: rotate(-5deg);
}

/* ===== TEXTOS DE ENCABEZADO ===== */
.header-text {
  text-align: center;
  margin-bottom: 24px;
}

.welcome-title {
  font-size: 26px;
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

/* ===== CAMPOS DE ENTRADA ===== */
.form-container {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.input-row {
  display: flex;
  gap: 10px;
}

.input-row .custom-input-item {
  flex: 1;
}

.custom-input-item {
  --background: rgba(255, 255, 255, 0.04);
  --border-radius: 16px;
  --padding-start: 14px;
  --highlight-color-focused: var(--app-accent, #10b981);
  border: 1px solid rgba(255, 255, 255, 0.06);
  transition: all 0.2s ease;
}

.custom-input-item:focus-within {
  border-color: rgba(16, 185, 129, 0.5);
  box-shadow: 0 0 15px rgba(16, 185, 129, 0.15);
}

.custom-input {
  --color: #ffffff;
  --placeholder-color: #64748b;
  --highlight-color-focused: var(--app-accent, #10b981);
  color: #ffffff;
}

/* ===== BOTONES ===== */
.btn-primary-glow {
  margin-top: 8px;
  --background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
  --color: #ffffff;
  --border-radius: 16px;
  --box-shadow: 0 8px 25px rgba(16, 185, 129, 0.35);
  font-weight: 700;
  font-size: 16px;
  height: 50px;
  letter-spacing: 0.3px;
  text-transform: none;
}

.btn-secondary-glass {
  margin-top: 2px;
  --color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  text-transform: none;
}

.highlight {
  color: #2dd4bf;
  font-weight: 700;
  margin-left: 4px;
}
</style>