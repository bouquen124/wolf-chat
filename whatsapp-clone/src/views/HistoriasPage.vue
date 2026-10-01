<template>
  <ion-page class="historias-page">
    <ion-header class="glass-header">
      <ion-toolbar> <ion-title>Historias</ion-title> </ion-toolbar>
    </ion-header>
    <ion-content :fullscreen="true">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="contenido">
        <ion-list class="mi-historia-list">
          <ion-list-header>
            <ion-label>Mi historia</ion-label>
          </ion-list-header>

          <ion-item button class="mi-historia-card" lines="none">
            <div class="mi-avatar-wrapper" slot="start">
              <ion-avatar class="mi-avatar">
                <img src="https://i.pravatar.cc/150?img=12" />
              </ion-avatar>

              <div class="boton-agregar">+</div>
            </div>

            <ion-label>
              <h2>Mi historia</h2>
              <p>Agregar una actualización</p>
            </ion-label>
          </ion-item>
        </ion-list>

        <div class="seccion-historias">
          <h3>Historias recientes</h3>

          <div class="historias">
            <div
              v-for="historia in historias"
              :key="historia.id"
              class="historia"
              @click="$router.push(`/historia/${historia.id}`)"
            >
              <div
                :class="[
                  'historia-avatar',
                  historia.vista ? 'vista' : 'no-vista',
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
            <div
              v-for="historia in historiasVistas"
              :key="historia.id"
              class="historia"
              @click="$router.push(`/historia/${historia.id}`)"
            >
              <div class="historia-avatar vista">
                <img :src="historia.avatar" />
              </div>

              <span>{{ historia.nombre }}</span>
            </div>
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
  IonAvatar,
} from "@ionic/vue";
const historias = [
  {
    id: 1,
    nombre: "Juan",
    avatar: "https://i.pravatar.cc/150?img=12",
    vista: false,
  },
  {
    id: 2,
    nombre: "María",
    avatar: "https://i.pravatar.cc/150?img=5",
    vista: false,
  },
  {
    id: 3,
    nombre: "Carlos",
    avatar: "https://i.pravatar.cc/150?img=3",
    vista: false,
  },
  {
    id: 4,
    nombre: "Ana",
    avatar: "https://i.pravatar.cc/150?img=9",
    vista: false,
  },
];
const historiasVistas = [
  { id: 5, nombre: "Luis", avatar: "https://i.pravatar.cc/150?img=8" },
  { id: 6, nombre: "Pedro", avatar: "https://i.pravatar.cc/150?img=14" },
];
</script>
<style scoped>
.historias-page {
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
  top: 330px;
  right: -130px;
  background: #06b6d4;
}
.mi-historia-list {
  background: transparent;
  padding: 0;
}
.mi-historia-list ion-list-header {
  padding: 10px 4px 12px;
  min-height: auto;
}
.mi-historia-list ion-list-header ion-label {
  color: #ffffff;
  font-size: 15px;
  font-weight: 650;
}
.mi-historia-card {
  --background: rgba(255, 255, 255, 0.045);
  --background-activated: rgba(255, 255, 255, 0.08);
  --color: #ffffff;
  --border-color: transparent;
  --inner-border-width: 0;
  --padding-start: 12px;
  --padding-end: 12px;
  --min-height: 76px;
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 20px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.18);
  overflow: visible;
}
.mi-avatar-wrapper {
  position: relative;
  width: 52px;
  height: 52px;
  margin-right: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.mi-avatar {
  width: 52px;
  height: 52px;
  padding: 2px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #06b6d4);
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.2);
}
.mi-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid #090d16;
}
.boton-agregar {
  position: absolute;
  right: -4px;
  bottom: -4px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #06b6d4);
  color: #ffffff;
  border: 2px solid #090d16;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  font-weight: 700;
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
}
.mi-historia-card h2 {
  margin: 0 0 5px;
  color: #ffffff;
  font-size: 16px;
  font-weight: 650;
}
.mi-historia-card p {
  margin: 0;
  color: #8993a5;
  font-size: 13px;
}
.seccion-historias {
  margin-top: 24px;
  padding: 0 4px;
}
.seccion-historias h3 {
  margin: 0 0 14px;
  color: #ffffff;
  font-size: 15px;
  font-weight: 650;
}
.historias {
  display: flex;
  gap: 18px;
  overflow-x: auto;
  padding: 4px 2px 12px;
  scrollbar-width: none;
}
.historias::-webkit-scrollbar {
  display: none;
}
.historia {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 68px;
  gap: 7px;
  cursor: pointer;
}
.historia span {
  font-size: 12px;
  color: #aab3c1;
  text-align: center;
  max-width: 68px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.historia-avatar {
  width: 66px;
  height: 66px;
  padding: 3px;
  border-radius: 50%;
  background: #090d16;
}
.historia-avatar.no-vista {
  background: linear-gradient(135deg, #10b981, #06b6d4);
  box-shadow: 0 0 18px rgba(16, 185, 129, 0.22);
}
.historia-avatar.vista {
  background: #4b5563;
  box-shadow: none;
}
.historia-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid #090d16;
}
</style>
