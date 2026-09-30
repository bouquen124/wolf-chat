<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <ion-title>Historias</ion-title>
      </ion-toolbar>
    </ion-header>


<ion-content>
  <ion-list>
    <ion-list-header>
      <ion-label>Mi historia</ion-label>
    </ion-list-header>

    <ion-item button>
      <ion-avatar slot="start" class="mi-avatar">
        <img src="https://i.pravatar.cc/150?img=12" />
        <div class="boton-agregar">+</div>
      </ion-avatar>

      <ion-label>
        <h2>Mi historia</h2>
        <p>Agregar una actualización</p>
      </ion-label>
    </ion-item>
  </ion-list>

  <div class="seccion-historias">
    <h3>Historias recientes</h3>

    <div class="historias">
    <div v-for="historia in historias" :key="historia.id" class="historia" @click="$router.push(`/historia/${historia.id}`)" >
        <div
          :class="[
            'historia-avatar',
            historia.vista ? 'vista' : 'no-vista'
          ]"
        >
          <img :src="historia.avatar" />
        </div>

        <span>{{ historia.nombre }}</span>
      </div>
    </div>
  </div>

  <div class="seccion-historias">
    <h3>Historias vistas</h3>

    <div class="historias">
     <div v-for="historia in historiasVistas" :key="historia.id" class="historia" @click="$router.push(`/historia/${historia.id}`)" >
        <div class="historia-avatar vista">
          <img :src="historia.avatar" />
        </div>

        <span>{{ historia.nombre }}</span>
      </div>
    </div>
  </div>
</ion-content>


  </ion-page>
</template>

<script setup lang="ts">
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonList,
  IonListHeader,
  IonLabel,
  IonItem,
  IonAvatar
} from '@ionic/vue';

const historias = [
  {
    id: 1,
    nombre: 'Juan',
    avatar: 'https://i.pravatar.cc/150?img=12',
    vista: false
  },
  {
    id: 2,
    nombre: 'María',
    avatar: 'https://i.pravatar.cc/150?img=5',
    vista: false
  },
  {
    id: 3,
    nombre: 'Carlos',
    avatar: 'https://i.pravatar.cc/150?img=3',
    vista: false
  },
  {
    id: 4,
    nombre: 'Ana',
    avatar: 'https://i.pravatar.cc/150?img=9',
    vista: false
  }
];

const historiasVistas = [
  {
    id: 5,
    nombre: 'Luis',
    avatar: 'https://i.pravatar.cc/150?img=8'
  },
  {
    id: 6,
    nombre: 'Pedro',
    avatar: 'https://i.pravatar.cc/150?img=14'
  }
];
</script>

<style scoped>
.seccion-historias {
  padding: 10px 16px 20px;
}

.seccion-historias h3 {
  margin: 8px 0 14px;
  font-size: 16px;
  font-weight: 600;
}

.historias {
  display: flex;
  gap: 18px;
  overflow-x: auto;
  padding: 4px 2px 10px;
}

.historia {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 68px;
  gap: 6px;
}

.historia span {
  font-size: 12px;
  text-align: center;
  max-width: 68px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.historia-avatar {
  width: 62px;
  height: 62px;
  padding: 3px;
  border-radius: 50%;
}

.historia-avatar.no-vista {
  border: 3px solid #25d366;
}

.historia-avatar.vista {
  border: 3px solid #9ca3af;
}

.historia-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.mi-avatar {
  position: relative;
  overflow: visible;
}

.boton-agregar {
  position: absolute;
  right: -3px;
  bottom: -3px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #25d366;
  color: white;
  border: 2px solid var(--ion-background-color);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: bold;
}
</style>
