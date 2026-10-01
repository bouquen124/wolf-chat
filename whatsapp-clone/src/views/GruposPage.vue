<template>
  <ion-page class="grupos-page">
    <ion-header class="glass-header">
      <ion-toolbar> <ion-title>Grupos</ion-title> </ion-toolbar>
    </ion-header>
    <ion-content :fullscreen="true">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="contenido">
        <ion-searchbar
          v-model="busqueda"
          placeholder="Buscar grupo"
          class="searchbar"
        />

        <ion-list class="grupos-list">
          <ion-item
            v-for="grupo in gruposFiltrados"
            :key="grupo.id"
            button
            :router-link="`/grupo/${grupo.id}`"
            class="grupo-card"
            lines="none"
          >
            <div class="avatar-ring" slot="start">
              <ion-avatar>
                <img :src="grupo.avatar" />
              </ion-avatar>
            </div>

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
      </div>

      <ion-fab slot="fixed" vertical="bottom" horizontal="end">
        <ion-fab-button class="fab-neon">
          <ion-icon :icon="addOutline" />
        </ion-fab-button>
      </ion-fab>
    </ion-content>
  </ion-page>
</template>
<script setup lang="ts">
import { computed, ref } from "vue";
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
} from "@ionic/vue";
import { addOutline } from "ionicons/icons";
const busqueda = ref("");
const grupos = [
  {
    id: 1,
    nombre: "Desarrollo",
    mensaje: "Juan: Ya quedó bro 🔥",
    hora: "15:42",
    noLeidos: 3,
    avatar: "https://i.pravatar.cc/150?img=11",
  },
  {
    id: 2,
    nombre: "Familia",
    mensaje: "Mamá: ¿A qué hora llegas?",
    hora: "14:36",
    noLeidos: 5,
    avatar: "https://i.pravatar.cc/150?img=47",
  },
  {
    id: 3,
    nombre: "Amigos",
    mensaje: "Carlos: Jajaja 😂",
    hora: "13:21",
    noLeidos: 0,
    avatar: "https://i.pravatar.cc/150?img=13",
  },
  {
    id: 4,
    nombre: "Proyecto App",
    mensaje: "María: Te mandé el archivo",
    hora: "12:08",
    noLeidos: 1,
    avatar: "https://i.pravatar.cc/150?img=32",
  },
  {
    id: 5,
    nombre: "Fin de semana",
    mensaje: "Luis: ¿Qué hacemos?",
    hora: "10:47",
    noLeidos: 2,
    avatar: "https://i.pravatar.cc/150?img=15",
  },
];
const gruposFiltrados = computed(() => {
  const texto = busqueda.value.toLowerCase();
  return grupos.filter((grupo) => grupo.nombre.toLowerCase().includes(texto));
});
</script>
<style scoped>
.grupos-page {
  --ion-background-color: #090d16;
  background: #090d16;
}
.glass-header {
  background: transparent;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
}
.glass-header ion-toolbar {
  --background: rgba(9, 13, 22, 0.72);
  --border-width: 0;
}
.glass-header ion-title {
  font-size: 24px;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: -0.5px;
}
ion-content {
  --background: #090d16;
  position: relative;
  overflow: hidden;
}
.contenido {
  position: relative;
  z-index: 2;
  padding: 8px 14px 100px;
}
.glow-emerald,
.glow-cyan {
  position: fixed;
  width: 260px;
  height: 260px;
  border-radius: 50%;
  filter: blur(90px);
  opacity: 0.18;
  pointer-events: none;
  z-index: 0;
}
.glow-emerald {
  top: 70px;
  left: -120px;
  background: #10b981;
}
.glow-cyan {
  top: 280px;
  right: -130px;
  background: #06b6d4;
}
.searchbar {
  --background: rgba(255, 255, 255, 0.055);
  --border-radius: 18px;
  --box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.07);
  --color: #ffffff;
  --placeholder-color: #7d8798;
  --icon-color: #10b981;
  margin: 4px 0 18px;
  padding: 0;
}
.grupos-list {
  background: transparent;
  padding: 0;
}
.grupo-card {
  --background: rgba(255, 255, 255, 0.045);
  --background-activated: rgba(255, 255, 255, 0.08);
  --color: #ffffff;
  --border-color: transparent;
  --inner-border-width: 0;
  --padding-start: 12px;
  --padding-end: 12px;
  --min-height: 76px;
  margin-bottom: 10px;
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 20px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.18);
  overflow: hidden;
}
.avatar-ring {
  width: 52px;
  height: 52px;
  padding: 2px;
  margin-right: 12px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #06b6d4);
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.18);
}
.avatar-ring ion-avatar {
  width: 48px;
  height: 48px;
  border: 2px solid #090d16;
}
.grupo-card h2 {
  margin: 0 0 5px;
  color: #ffffff;
  font-size: 16px;
  font-weight: 650;
}
.grupo-card p {
  margin: 0;
  color: #8993a5;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.grupo-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 7px;
  margin-left: 8px;
}
.hora {
  font-size: 11px;
  color: #697486;
}
.grupo-info ion-badge {
  --background: linear-gradient(135deg, #10b981, #06b6d4);
  --color: #ffffff;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  font-size: 11px;
  font-weight: 700;
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.25);
}
ion-fab {
  margin: 0 10px 14px 0;
}
.fab-neon {
  --background: linear-gradient(135deg, #10b981, #06b6d4);
  --background-activated: linear-gradient(135deg, #059669, #0891b2);
  --color: #ffffff;
  --box-shadow: 0 8px 28px rgba(16, 185, 129, 0.32);
  width: 58px;
  height: 58px;
}
</style>
