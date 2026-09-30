<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Grupos</ion-title>
      </ion-toolbar>
    </ion-header>


<ion-content>
  <ion-searchbar
    v-model="busqueda"
    placeholder="Buscar grupo"
  />

  <ion-list>
   

<ion-item
  v-for="grupo in gruposFiltrados"
  :key="grupo.id"
  button
  :router-link="`/grupo/${grupo.id}`"
>

      <ion-avatar slot="start">
        <img :src="grupo.avatar" />
      </ion-avatar>

      <ion-label>
        <h2>{{ grupo.nombre }}</h2>
        <p>{{ grupo.mensaje }}</p>
      </ion-label>

      <div slot="end" class="grupo-info">
        <span class="hora">{{ grupo.hora }}</span>

        <ion-badge v-if="grupo.noLeidos">
          {{ grupo.noLeidos }}
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
import { computed, ref } from 'vue';
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
  IonIcon
} from '@ionic/vue';

import { addOutline } from 'ionicons/icons';

const busqueda = ref('');

const grupos = [
  {
    id: 1,
    nombre: 'Desarrollo',
    mensaje: 'Juan: Ya quedó bro 🔥',
    hora: '15:42',
    noLeidos: 3,
    avatar: 'https://i.pravatar.cc/150?img=11'
  },
  {
    id: 2,
    nombre: 'Familia',
    mensaje: 'Mamá: ¿A qué hora llegas?',
    hora: '14:36',
    noLeidos: 5,
    avatar: 'https://i.pravatar.cc/150?img=47'
  },
  {
    id: 3,
    nombre: 'Amigos',
    mensaje: 'Carlos: Jajaja 😂',
    hora: '13:21',
    noLeidos: 0,
    avatar: 'https://i.pravatar.cc/150?img=13'
  },
  {
    id: 4,
    nombre: 'Proyecto App',
    mensaje: 'María: Te mandé el archivo',
    hora: '12:08',
    noLeidos: 1,
    avatar: 'https://i.pravatar.cc/150?img=32'
  },
  {
    id: 5,
    nombre: 'Fin de semana',
    mensaje: 'Luis: ¿Qué hacemos?',
    hora: '10:47',
    noLeidos: 2,
    avatar: 'https://i.pravatar.cc/150?img=15'
  }
];

const gruposFiltrados = computed(() => {
  const texto = busqueda.value.toLowerCase();

  return grupos.filter(grupo =>
    grupo.nombre.toLowerCase().includes(texto)
  );
});
</script>

<style scoped>
.grupo-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.hora {
  font-size: 12px;
  color: var(--ion-color-medium);
}
</style>
