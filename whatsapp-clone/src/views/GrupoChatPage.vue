<template>
  <ion-page class="grupo-chat-page">
    <ion-header class="glass-header">
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/grupos" />
        </ion-buttons>
        <div class="header-contacto">
          <div class="avatar-ring-header">
            <ion-avatar class="avatar-header">
              <img :src="grupo.avatar" />
            </ion-avatar>
          </div>

          <ion-title>{{ grupo.nombre }}</ion-title>
        </div>
      </ion-toolbar>
    </ion-header>

    <ion-content class="chat-content">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="mensajes">
        <div
          v-for="mensaje in mensajes"
          :key="mensaje.id"
          :class="[
            'mensaje',
            mensaje.usuario === 'yo' ? 'mensaje-yo' : 'mensaje-otro',
          ]"
        >
          <div class="burbuja">
            <strong v-if="mensaje.usuario !== 'yo'">
              {{ mensaje.nombre }}
            </strong>

            <span>{{ mensaje.texto }}</span>

            <small>{{ mensaje.hora }}</small>
          </div>
        </div>
      </div>
    </ion-content>

    <ion-footer class="glass-footer">
      <div class="barra-mensaje">
        <ion-input
          v-model="nuevoMensaje"
          placeholder="Escribe un mensaje..."
          @keyup.enter="enviarMensaje"
        />

        <ion-button class="send-button" @click="enviarMensaje">
          <ion-icon :icon="sendOutline" />
        </ion-button>
      </div>
    </ion-footer>
  </ion-page>
</template>
<script setup lang="ts">
import { ref } from "vue";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonBackButton,
  IonAvatar,
  IonContent,
  IonFooter,
  IonInput,
  IonButton,
  IonIcon,
} from "@ionic/vue";
import { sendOutline } from "ionicons/icons";
const grupo = {
  nombre: "Desarrollo",
  avatar: "https://i.pravatar.cc/150?img=11",
};
const nuevoMensaje = ref("");
const mensajes = ref([
  {
    id: 1,
    usuario: "juan",
    nombre: "Juan",
    texto: "¿Ya quedó la app?",
    hora: "15:30",
  },
  {
    id: 2,
    usuario: "maria",
    nombre: "María",
    texto: "Yo ya terminé mi parte.",
    hora: "15:32",
  },
  {
    id: 3,
    usuario: "yo",
    nombre: "Rodrigo",
    texto: "Excelente bro 🔥",
    hora: "15:35",
  },
]);
const enviarMensaje = () => {
  const texto = nuevoMensaje.value.trim();
  if (!texto) {
    return;
  }
  mensajes.value.push({
    id: Date.now(),
    usuario: "yo",
    nombre: "Rodrigo",
    texto,
    hora: new Date().toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    }),
  });
  nuevoMensaje.value = "";
};
</script>
<style scoped>
.grupo-chat-page {
  --ion-background-color: #090d16;
  background: #090d16;
}
.glass-header {
  background: transparent;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
}
.glass-header ion-toolbar {
  --background: rgba(9, 13, 22, 0.78);
  --border-width: 0;
}
.glass-header ion-buttons ion-back-button {
  --color: #ffffff;
}
.header-contacto {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}
.header-contacto ion-title {
  padding-left: 10px;
  font-size: 18px;
  font-weight: 650;
  color: #ffffff;
}
.avatar-ring-header {
  width: 40px;
  height: 40px;
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #06b6d4);
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.22);
}
.avatar-header {
  width: 36px;
  height: 36px;
  border: 2px solid #090d16;
}
.avatar-header img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.chat-content {
  --background: #090d16;
  position: relative;
}
.glow-emerald,
.glow-cyan {
  position: fixed;
  width: 280px;
  height: 280px;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.14;
  pointer-events: none;
  z-index: 0;
}
.glow-emerald {
  top: 40px;
  left: -140px;
  background: #10b981;
}
.glow-cyan {
  bottom: 80px;
  right: -140px;
  background: #06b6d4;
}
.mensajes {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px 14px 20px;
  min-height: 100%;
}
.mensaje {
  display: flex;
}
.mensaje-yo {
  justify-content: flex-end;
}
.mensaje-otro {
  justify-content: flex-start;
}
.burbuja {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  max-width: 80%;
  padding: 10px 12px;
  border-radius: 18px;
  font-size: 15px;
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 6px 22px rgba(0, 0, 0, 0.16);
}
.mensaje-yo .burbuja {
  background: linear-gradient(
    135deg,
    rgba(16, 185, 129, 0.28),
    rgba(6, 182, 212, 0.22)
  );
  border-color: rgba(16, 185, 129, 0.18);
  border-bottom-right-radius: 5px;
}
.mensaje-otro .burbuja {
  background: rgba(255, 255, 255, 0.055);
  border-bottom-left-radius: 5px;
}
.burbuja strong {
  font-size: 12px;
  color: #10b981;
  margin-bottom: 4px;
}
.burbuja span {
  line-height: 1.4;
  color: #f2f5f8;
}
.burbuja small {
  align-self: flex-end;
  font-size: 10px;
  color: #7c8797;
  margin-top: 5px;
}
.glass-footer {
  background: rgba(9, 13, 22, 0.82);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}
.glass-footer::before {
  display: none;
}
.barra-mensaje {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: transparent;
}
.barra-mensaje ion-input {
  --background: rgba(255, 255, 255, 0.055);
  --color: #ffffff;
  --placeholder-color: #7d8798;
  --padding-start: 16px;
  --padding-end: 16px;
  --padding-top: 11px;
  --padding-bottom: 11px;
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 18px;
}
.send-button {
  --background: linear-gradient(135deg, #10b981, #06b6d4);
  --background-activated: linear-gradient(135deg, #059669, #0891b2);
  --color: #ffffff;
  --border-radius: 16px;
  --box-shadow: 0 0 18px rgba(16, 185, 129, 0.24);
  width: 46px;
  height: 46px;
  margin: 0;
}
.send-button ion-icon {
  font-size: 21px;
}
</style>
