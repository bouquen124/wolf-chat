<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Chats</ion-title>
      </ion-toolbar>
    </ion-header>


<ion-content>
  <ion-searchbar
    v-model="busqueda"
    placeholder="Buscar"
    :debounce="300"
  />

  <ion-list>
    <ion-item
      v-for="chat in chatsFiltrados"
      :key="chat.id"
      button
    >
      <ion-avatar slot="start">
        <img :src="chat.foto" :alt="chat.nombre" />
      </ion-avatar>

      <ion-label>
        <h2>{{ chat.nombre }}</h2>
        <p>{{ chat.ultimoMensaje }}</p>
      </ion-label>

      <div slot="end" class="chat-info">
        <span class="hora">{{ chat.hora }}</span>

        <ion-badge v-if="chat.noLeidos > 0">
          {{ chat.noLeidos }}
        </ion-badge>
      </div>
    </ion-item>
  </ion-list>

  <ion-fab slot="fixed" vertical="bottom" horizontal="end">
    <ion-fab-button>
      <ion-icon :icon="addOutline" />
    </ion-fab-button>
  </ion-fab>
</ion-content>


  </ion-page>
</template>

<script setup lang="ts">
import {
  IonAvatar,
  IonBadge,
  IonContent,
  IonFab,
  IonFabButton,
  IonHeader,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonPage,
  IonSearchbar,
  IonTitle,
  IonToolbar
} from '@ionic/vue';

import { addOutline } from 'ionicons/icons';
import { computed, ref } from 'vue';

interface Chat {
  id: number;
  nombre: string;
  foto: string;
  ultimoMensaje: string;
  hora: string;
  noLeidos: number;
}

const busqueda = ref('');

const chats = ref<Chat[]>([
  {
    id: 1,
    nombre: 'Juan',
    foto: 'https://i.pravatar.cc/150?img=12',
    ultimoMensaje: '¿Qué onda bro?',
    hora: '14:32',
    noLeidos: 2
  },
  {
    id: 2,
    nombre: 'María',
    foto: 'https://i.pravatar.cc/150?img=47',
    ultimoMensaje: 'Nos vemos mañana',
    hora: '13:18',
    noLeidos: 0
  },
  {
    id: 3,
    nombre: 'Pedro',
    foto: 'https://i.pravatar.cc/150?img=33',
    ultimoMensaje: '👍',
    hora: '12:45',
    noLeidos: 5
  },
  {
    id: 4,
    nombre: 'Laura',
    foto: 'https://i.pravatar.cc/150?img=44',
    ultimoMensaje: 'Ya llegué',
    hora: '11:20',
    noLeidos: 0
  }
]);

const chatsFiltrados = computed(() => {
  const texto = busqueda.value.toLowerCase().trim();

  if (!texto) {
    return chats.value;
  }

  return chats.value.filter(chat =>
    chat.nombre.toLowerCase().includes(texto)
  );
});
</script>

<style scoped>
ion-content {
  --background: #ffffff;
}

ion-toolbar {
  --background: #ffffff;
}

ion-title {
  font-weight: 700;
}

ion-searchbar {
  --background: #f1f1f1;
  --box-shadow: none;
  --border-radius: 12px;
  padding: 8px 12px;
}

ion-item {
  --padding-start: 12px;
  --inner-padding-end: 12px;
  --min-height: 72px;
}

ion-avatar {
  width: 52px;
  height: 52px;
}

ion-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

ion-label h2 {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 5px;
}

ion-label p {
  color: #777;
  font-size: 14px;
  margin: 0;
}

.chat-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.hora {
  font-size: 12px;
  color: #888;
}

ion-badge {
  font-size: 11px;
  min-width: 20px;
  height: 20px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

ion-fab-button {
  --border-radius: 16px;
}
</style>
