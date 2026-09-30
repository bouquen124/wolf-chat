<!-- C:\xampp\htdocs\new app\whatsapp-clone\src\views\AgregarContactoPage.vue -->

<template>
  <ion-page class="agregar-contacto-page">
    <ion-header class="ion-no-border header-transparent">
      <ion-toolbar class="toolbar-transparent">
        <ion-buttons slot="start">
          <ion-back-button default-href="/chats" class="custom-back-btn" />
        </ion-buttons>

        <ion-title class="header-title">Agregar contacto</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="agregar-contacto-content" :fullscreen="true">
      <!-- Luces ambientales de fondo (Aurora Glow) -->
      <div class="ambient-glow glow-emerald"></div>
      <div class="ambient-glow glow-cyan"></div>

      <div class="contenedor">
        <!-- Buscador con estilo Glass -->
        <div class="search-box">
          <ion-searchbar
            v-model="telefono"
            placeholder="Buscar por teléfono..."
            inputmode="tel"
            class="custom-searchbar"
            @keyup.enter="buscarUsuario"
          />

          <ion-button
            expand="block"
            class="btn-primary-glow search-btn"
            @click="buscarUsuario"
          >
            Buscar
          </ion-button>
        </div>

        <!-- Tarjeta de Usuario Encontrado -->
        <div
          v-if="usuario"
          class="usuario-card"
        >
          <div class="avatar-container">
            <div class="avatar-ring">
              <ion-avatar class="custom-avatar">
                <img :src="usuario.foto || 'https://i.pravatar.cc/150?img=12'" alt="Avatar" />
              </ion-avatar>
            </div>
          </div>

          <div class="datos">
            <h2>{{ usuario.nombre }} {{ usuario.apellido }}</h2>
            <p>{{ usuario.telefono }}</p>
          </div>

          <ion-button
            v-if="!yaAgregado"
            size="small"
            class="btn-add-glow"
            @click="agregarContacto"
          >
            Agregar
          </ion-button>

          <span
            v-else
            class="badge-agregado"
          >
            Ya agregado
          </span>
        </div>

        <!-- Mensaje de estado / error -->
        <div v-if="mensaje" class="mensaje-card">
          <p class="mensaje-texto">
            {{ mensaje }}
          </p>
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
  IonButtons,
  IonBackButton,
  IonTitle,
  IonContent,
  IonSearchbar,
  IonButton,
  IonAvatar
} from '@ionic/vue';

interface Usuario {
  id: number;
  telefono: string;
  nombre: string;
  apellido: string;
  foto: string | null;
}

interface Contacto {
  contacto_id: number;
}

const router = useRouter();

const telefono = ref('');
const usuario = ref<Usuario | null>(null);
const mensaje = ref('');
const yaAgregado = ref(false);

const buscarUsuario = async () => {
  const numero = telefono.value.trim();

  usuario.value = null;
  mensaje.value = '';
  yaAgregado.value = false;

  if (!numero) {
    mensaje.value = 'Escribe un número de teléfono';
    return;
  }

  try {
    const token = localStorage.getItem('token');

    if (!token) {
      alert('Sesión no válida');
      return;
    }

    const respuesta = await fetch(
      'http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=getUsuarios'
    );

    const datos = await respuesta.json();

    if (!datos.ok) {
      mensaje.value = datos.error;
      return;
    }

    const encontrado = datos.usuarios.find(
      (item: Usuario) => item.telefono === numero
    );

    if (!encontrado) {
      mensaje.value = 'No se encontró ningún usuario con ese teléfono';
      return;
    }

    usuario.value = encontrado;

    const datosContactos = new URLSearchParams();

    datosContactos.append('token', token);

    const respuestaContactos = await fetch(
      'http://localhost/new%20app/api/controladores/contactos_controlador.php?task=obtenerContactos',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: datosContactos.toString()
      }
    );

    const resultadoContactos = await respuestaContactos.json();

    if (!resultadoContactos.ok) {
      mensaje.value = resultadoContactos.error;
      return;
    }

    yaAgregado.value = resultadoContactos.contactos.some(
      (contacto: Contacto) =>
        Number(contacto.contacto_id) === Number(encontrado.id)
    );
  }
  catch (error) {
    console.error('Error al buscar usuario:', error);
    mensaje.value = 'Error al buscar el usuario';
  }
};

const agregarContacto = async () => {
  if (!usuario.value) {
    return;
  }

  try {
    const token = localStorage.getItem('token');

    if (!token) {
      alert('Sesión no válida');
      return;
    }

    const datos = new URLSearchParams();

    datos.append('token', token);
    datos.append('contacto_id', usuario.value.id.toString());

    const respuesta = await fetch(
      'http://localhost/new%20app/api/controladores/contactos_controlador.php?task=agregarContacto',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: datos.toString()
      }
    );

    const resultado = await respuesta.json();

    if (!resultado.ok) {
      alert(resultado.error);
      return;
    }

    yaAgregado.value = true;

    alert('Contacto agregado correctamente');

    router.push('/chats');
  }
  catch (error) {
    console.error('Error al agregar contacto:', error);
    alert('Error al agregar el contacto');
  }
};
</script>

<style scoped>
/* ===== ESTILO GLOBAL Y FONDO ===== */
.agregar-contacto-page {
  --background: #090d16;
  background-color: #090d16;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.agregar-contacto-content {
  --background: #090d16;
  position: relative;
}

/* ===== HEADER Y NAVBAR ===== */
.header-transparent {
  background: transparent;
}

.toolbar-transparent {
  --background: #090d16;
  --border-width: 0;
  padding-top: 6px;
}

.custom-back-btn {
  --color: #10b981;
}

.header-title {
  color: #ffffff;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.4px;
}

/* ===== AURORA GLOWS ===== */
.ambient-glow {
  position: fixed;
  border-radius: 50%;
  filter: blur(90px);
  pointer-events: none;
  z-index: 0;
  opacity: 0.35;
}

.glow-emerald {
  width: 280px;
  height: 280px;
  background: #10b981;
  top: -40px;
  right: -50px;
}

.glow-cyan {
  width: 250px;
  height: 250px;
  background: #06b6d4;
  bottom: 100px;
  left: -70px;
}

/* ===== CONTENEDOR PRINCIPAL ===== */
.contenedor {
  padding: 16px 18px;
  position: relative;
  z-index: 1;
}

/* ===== CONTENEDOR DE BÚSQUEDA ===== */
.search-box {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.custom-searchbar {
  --background: rgba(255, 255, 255, 0.05);
  --color: #ffffff;
  --placeholder-color: #64748b;
  --icon-color: #10b981;
  --clear-button-color: #94a3b8;
  --border-radius: 18px;
  --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  padding: 0;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 18px;
  backdrop-filter: blur(12px);
}

.btn-primary-glow {
  --background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
  --color: #ffffff;
  --border-radius: 16px;
  --box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
  font-weight: 700;
  font-size: 15px;
  height: 48px;
  margin: 0;
  text-transform: none;
}

/* ===== TARJETA DE USUARIO ===== */
.usuario-card {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  padding: 16px;
  border-radius: 20px;
  background: rgba(18, 24, 38, 0.65);
  border: 1px solid rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

/* AVATAR CON ANILLO NEÓN */
.avatar-container {
  flex-shrink: 0;
}

.avatar-ring {
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
}

.custom-avatar {
  width: 52px;
  height: 52px;
}

.custom-avatar img {
  border-radius: 50%;
  object-fit: cover;
}

/* DATOS DE USUARIO */
.datos {
  flex: 1;
  min-width: 0;
}

.datos h2 {
  margin: 0 0 4px 0;
  font-size: 16px;
  font-weight: 700;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.datos p {
  margin: 0;
  font-size: 13px;
  color: #8a99ad;
  font-weight: 500;
}

/* BOTÓN DE AGREGAR */
.btn-add-glow {
  --background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  --color: #ffffff;
  --border-radius: 12px;
  --box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
  font-weight: 700;
  font-size: 13px;
  height: 36px;
  text-transform: none;
  flex-shrink: 0;
}

/* BADGE YA AGREGADO */
.badge-agregado {
  font-size: 12px;
  font-weight: 700;
  color: #06b6d4;
  background: rgba(6, 182, 212, 0.12);
  border: 1px solid rgba(6, 182, 212, 0.3);
  padding: 6px 12px;
  border-radius: 20px;
  flex-shrink: 0;
  box-shadow: 0 0 10px rgba(6, 182, 212, 0.15);
}

/* ===== MENSAJE CARD ===== */
.mensaje-card {
  margin-top: 24px;
  padding: 16px;
  background: rgba(18, 24, 38, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  backdrop-filter: blur(12px);
  text-align: center;
}

.mensaje-texto {
  margin: 0;
  color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.5;
}
</style>