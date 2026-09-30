<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button default-href="/grupos" />
        </ion-buttons>


    <ion-avatar class="avatar-header">
      <img :src="grupo.avatar" />
    </ion-avatar>

    <ion-title>{{ grupo.nombre }}</ion-title>
  </ion-toolbar>
</ion-header>

<ion-content class="chat-content">
  <div class="mensajes">
    <div
      v-for="mensaje in mensajes"
      :key="mensaje.id"
      :class="[
        'mensaje',
        mensaje.usuario === 'yo' ? 'mensaje-yo' : 'mensaje-otro'
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

<ion-footer>
  <div class="barra-mensaje">
    <ion-input
      v-model="nuevoMensaje"
      placeholder="Escribe un mensaje..."
      @keyup.enter="enviarMensaje"
    />

    <ion-button @click="enviarMensaje">
      <ion-icon :icon="sendOutline" />
    </ion-button>
  </div>
</ion-footer>


  </ion-page>
</template>

<script setup lang="ts">
import { ref } from 'vue';
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
  IonIcon
} from '@ionic/vue';

import { sendOutline } from 'ionicons/icons';

const grupo = {
  nombre: 'Desarrollo',
  avatar: 'https://i.pravatar.cc/150?img=11'
};

const nuevoMensaje = ref('');

const mensajes = ref([
  {
    id: 1,
    usuario: 'juan',
    nombre: 'Juan',
    texto: '¿Ya quedó la app?',
    hora: '15:30'
  },
  {
    id: 2,
    usuario: 'maria',
    nombre: 'María',
    texto: 'Yo ya terminé mi parte.',
    hora: '15:32'
  },
  {
    id: 3,
    usuario: 'yo',
    nombre: 'Rodrigo',
    texto: 'Excelente bro 🔥',
    hora: '15:35'
  }
]);

const enviarMensaje = () => {
  const texto = nuevoMensaje.value.trim();

  if (!texto) {
    return;
  }

  mensajes.value.push({
    id: Date.now(),
    usuario: 'yo',
    nombre: 'Rodrigo',
    texto,
    hora: new Date().toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit'
    })
  });

  nuevoMensaje.value = '';
};
</script>

<style scoped>
.chat-content {
  --background: #111827;
}

.mensajes {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px 14px;
  min-height: 100%;
  background:
    radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.08), transparent 30%),
    radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.08), transparent 30%),
    #111827;
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
  padding: 8px 10px;
  border-radius: 12px;
  font-size: 15px;
  color: #111827;
}

.mensaje-yo .burbuja {
  background: #d9fdd3;
}

.mensaje-otro .burbuja {
  background: #ffffff;
}

.burbuja strong {
  font-size: 12px;
  color: #6366f1;
  margin-bottom: 3px;
}

.burbuja small {
  align-self: flex-end;
  font-size: 10px;
  color: #667781;
  margin-top: 4px;
}

.barra-mensaje {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  background: var(--ion-background-color);
}

.barra-mensaje ion-input {
  flex: 1;
}

.avatar-header {
  width: 36px;
  height: 36px;
  margin-left: 4px;
  margin-right: 8px;
}

.avatar-header img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
