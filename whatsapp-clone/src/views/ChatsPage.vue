<!-- C:\xampp\htdocs\new app\whatsapp-clone\src\views\ChatsPage.vue -->

<template>
  <ion-page class="chats-page">
    <ion-header class="ion-no-border header-transparent">
      <ion-toolbar class="toolbar-transparent">
        <ion-title class="header-title">Chats</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="chats-content" :fullscreen="true">
      <!-- Luces ambientales de fondo (Aurora Glow) -->
      <div class="ambient-glow glow-emerald"></div>
      <div class="ambient-glow glow-cyan"></div>

      <div class="chats-wrapper">
        <!-- Buscador con estilo Glass -->
        <div class="search-container">
          <ion-searchbar
            v-model="busqueda"
            placeholder="Buscar chat o contacto..."
            class="custom-searchbar"
          />
        </div>

        <!-- Lista de conversaciones -->
        <ion-list class="chats-list" v-if="chatsFiltrados.length > 0">
          <ion-item
            v-for="chat in chatsFiltrados"
            :key="chat.contacto_id"
            button
            :detail="false"
            class="chat-card-item"
            @click="abrirChat(chat)"
          >
            <!-- Avatar con anillo neón -->
            <div class="avatar-container" slot="start">
              <div class="avatar-ring">
                <ion-avatar class="custom-avatar">
                  <img v-if="chat.avatar" :src="chat.avatar" alt="Avatar" />
                </ion-avatar>
              </div>
            </div>

            <!-- Información del contacto y último mensaje -->
            <ion-label class="chat-label">
              <h2 class="chat-name">{{ chat.nombre }}</h2>
              <p class="chat-message">
                {{ chat.mensaje || "Sin mensajes en esta conversación" }}
              </p>
            </ion-label>

            <!-- Hora y Badge de mensajes no leídos -->
            <div slot="end" class="chat-info">
              <span v-if="chat.hora" class="hora">
                {{ chat.hora }}
              </span>

              <ion-badge v-if="chat.no_leidos > 0" class="neon-badge">
                {{ chat.no_leidos }}
              </ion-badge>
            </div>
          </ion-item>
        </ion-list>

        <!-- Estado vacío (sin chats) -->
        <div
          v-if="!cargando && chatsFiltrados.length === 0"
          class="sin-chats-card"
        >
          <div class="empty-icon-orb">
            <ion-icon :icon="addOutline" />
          </div>
          <h3 class="empty-title">Sin conversaciones</h3>
          <p class="empty-subtitle">
            Aún no tienes chats abiertos. Agrega a un contacto para comenzar a
            chatear.
          </p>

          <ion-button class="btn-primary-glow" @click="agregarContacto">
            Agregar contacto
          </ion-button>
        </div>
      </div>

      <!-- Botón Flotante Neón (FAB) -->
      <ion-fab
        slot="fixed"
        vertical="bottom"
        horizontal="end"
        class="custom-fab"
      >
        <ion-fab-button class="btn-fab-glow" @click="agregarContacto">
          <ion-icon :icon="addOutline" />
        </ion-fab-button>
      </ion-fab>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { useRouter } from "vue-router";

import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonSearchbar,
  IonList,
  IonItem,
  IonAvatar,
  IonLabel,
  IonBadge,
  IonFab,
  IonFabButton,
  IonIcon,
  IonButton,
  onIonViewDidLeave,
  onIonViewWillEnter,
} from "@ionic/vue";

import { addOutline } from "ionicons/icons";

interface Chat {
  conversacion_id: number | null;
  contacto_id: number;
  nombre: string;
  apellido: string;
  foto: string | null;
  mensaje: string | null;
  mensaje_tipo: string | null;
  fecha_envio: string | null;
  no_leidos: number;
  avatar?: string;
  hora?: string;
}

const router = useRouter();

const busqueda = ref("");
const chats = ref<Chat[]>([]);
const cargando = ref(false);

let intervaloChats: ReturnType<typeof setInterval> | null = null;
let solicitudChatsEnCurso = false;

const cargarChats = async () => {
  if (solicitudChatsEnCurso) return;
  solicitudChatsEnCurso = true;

  try {
    const token = localStorage.getItem("token");

    if (!token) {
      alert("Sesión no válida");
      return;
    }

    cargando.value = true;

    const datos = new URLSearchParams();
    datos.append("token", token);

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=obtenerChats",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    console.log("RESPUESTA CHATS:", resultado);

    if (!resultado.ok) {
      console.error(resultado.error);
      return;
    }

    chats.value = resultado.chats.map((chat: Chat) => ({
      ...chat,
      nombre: `${chat.nombre} ${chat.apellido}`.trim(),
      avatar: chat.foto || "",
      hora: chat.fecha_envio ? formatearHora(chat.fecha_envio) : "",
    }));
  } catch (error) {
    console.error("Error al cargar chats:", error);
  } finally {
    solicitudChatsEnCurso = false;
    cargando.value = false;
  }
};

const crearConversacion = async (contactoId: number) => {
  const token = localStorage.getItem("token");

  if (!token) {
    alert("Sesión no válida");
    return null;
  }

  try {
    const datos = new URLSearchParams();
    datos.append("token", token);
    datos.append("destinatario_id", contactoId.toString());

    const respuesta = await fetch(
      "https://eqtc5661rodrigo.tail4cd307.ts.net/controladores/conversaciones_controlador.php?task=crearConversacion",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: datos.toString(),
      },
    );

    const resultado = await respuesta.json();

    console.log("RESPUESTA CREAR CONVERSACION:", resultado);

    if (!resultado.ok) {
      alert(resultado.error);
      return null;
    }

    return Number(resultado.conversacion_id);
  } catch (error) {
    console.error("Error al crear conversación:", error);
    alert("Error al crear la conversación");
    return null;
  }
};

const abrirChat = async (chat: Chat) => {
  let conversacionId = chat.conversacion_id;

  if (!conversacionId) {
    conversacionId = await crearConversacion(chat.contacto_id);

    if (!conversacionId) {
      return;
    }
  }

  router.push(`/chat/${conversacionId}`);
};

const agregarContacto = () => {
  router.push("/agregar-contacto");
};

const formatearHora = (fecha: string) => {
  return new Date(fecha).toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });
};

const chatsFiltrados = computed(() => {
  const texto = busqueda.value.toLowerCase().trim();

  if (!texto) {
    return chats.value;
  }

  return chats.value.filter((chat) =>
    chat.nombre.toLowerCase().includes(texto),
  );
});

onIonViewWillEnter(() => {
  cargarChats();

  if (!intervaloChats) {
    intervaloChats = setInterval(() => {
      cargarChats();
    }, 2000);
  }
});

onIonViewDidLeave(() => {
  if (intervaloChats) {
    clearInterval(intervaloChats);
    intervaloChats = null;
  }
});
</script>

<style scoped>
/* ===== ESTILO GLOBAL Y FONDO ===== */
.chats-page {
  --background: #090d16;
  background-color: #090d16;
  font-family:
    -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.chats-content {
  --background: #090d16;
  position: relative;
}

/* HEADER TRANSPARENTE */
.header-transparent {
  background: transparent;
}

.toolbar-transparent {
  --background: #090d16;
  --border-width: 0;
  padding-top: 6px;
}

.header-title {
  color: #ffffff;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.5px;
}

/* ===== AURORA GLOWS DE FONDO ===== */
.ambient-glow {
  position: fixed;
  border-radius: 50%;
  filter: blur(90px);
  pointer-events: none;
  z-index: 0;
  opacity: 0.35;
}

.glow-emerald {
  width: 300px;
  height: 300px;
  background: var(--app-accent, #10b981);
  top: -60px;
  right: -60px;
}

.glow-cyan {
  width: 260px;
  height: 260px;
  background: var(--app-accent-secondary, #06b6d4);
  bottom: 80px;
  left: -80px;
}

/* ===== CONTENEDOR PRINCIPAL ===== */
.chats-wrapper {
  padding: 10px 14px 80px 14px;
  position: relative;
  z-index: 1;
}

/* ===== SEARCHBAR GLASS ===== */
.search-container {
  margin-bottom: 16px;
}

.custom-searchbar {
  --background: rgba(255, 255, 255, 0.05);
  --color: #ffffff;
  --placeholder-color: #64748b;
  --icon-color: var(--app-accent, #10b981);
  --clear-button-color: #94a3b8;
  --border-radius: 18px;
  --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  padding: 0;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 18px;
  backdrop-filter: blur(12px);
}

/* ===== LISTA DE CHATS ===== */
.chats-list {
  background: transparent;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.chat-card-item {
  --background: rgba(18, 24, 38, 0.65);
  --border-color: transparent;
  --padding-start: 14px;
  --padding-end: 14px;
  --inner-padding-end: 0;
  --min-height: 72px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.07);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  transition: all 0.25 ease;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
  margin-bottom: 2px;
}

.chat-card-item:active {
  transform: scale(0.98);
  --background: rgba(30, 41, 59, 0.8);
  border-color: rgba(16, 185, 129, 0.4);
}

/* ===== AVATAR NEÓN ===== */
.avatar-container {
  margin-right: 12px;
}

.avatar-ring {
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
}

.custom-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
}

.custom-avatar img {
  border-radius: 50%;
  object-fit: cover;
}

/* ===== TEXTO DEL CHAT ===== */
.chat-label {
  margin-top: 4px;
  margin-bottom: 4px;
}

.chat-name {
  color: #ffffff;
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 4px;
  letter-spacing: -0.2px;
}

.chat-message {
  color: #8a99ad;
  font-size: 13px;
  font-weight: 400;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 200px;
}

/* ===== INFO DERECHA (HORA & BADGE) ===== */
.chat-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.hora {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}

.neon-badge {
  --background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, #059669 100%);
  --color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  border-radius: 12px;
  padding: 4px 8px;
  box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
}

/* ===== ESTADO VACÍO (SIN CHATS) ===== */
.sin-chats-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  margin-top: 40px;
  text-align: center;
  background: rgba(18, 24, 38, 0.5);
  backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 24px;
}

.empty-icon-orb {
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.empty-icon-orb ion-icon {
  font-size: 32px;
  color: #ffffff;
}

.empty-title {
  color: #ffffff;
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 8px 0;
}

.empty-subtitle {
  color: #8a99ad;
  font-size: 13px;
  max-width: 260px;
  margin: 0 0 20px 0;
  line-height: 1.4;
}

/* ===== BOTÓN NEÓN ===== */
.btn-primary-glow {
  --background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
  --color: #ffffff;
  --border-radius: 14px;
  --box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
  font-weight: 700;
  font-size: 14px;
  height: 44px;
  text-transform: none;
}

/* ===== BOTÓN FLOTANTE (FAB) ===== */
.custom-fab {
  margin-bottom: 12px;
  margin-right: 8px;
}

.btn-fab-glow {
  --background: linear-gradient(135deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
  --color: #ffffff;
  --box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
  --border-radius: 18px;
  width: 56px;
  height: 56px;
}

.btn-fab-glow ion-icon {
  font-size: 26px;
}
</style>
