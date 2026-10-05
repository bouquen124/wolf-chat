<!-- C:\xampp\htdocs\new app\whatsapp-clone\src\components\TabBar.vue -->

<template>
  <ion-tab-bar slot="bottom" class="custom-tab-bar">
    <ion-tab-button tab="chats" href="/chats" class="custom-tab-button">
      <ion-icon :icon="chatbubblesOutline" />
      <ion-label>Chats</ion-label>
    </ion-tab-button>

    <ion-tab-button tab="grupos" href="/grupos" class="custom-tab-button">
      <ion-icon :icon="peopleOutline" />
      <ion-label>Grupos</ion-label>
    </ion-tab-button>

    <ion-tab-button tab="historias" href="/historias" class="custom-tab-button">
      <ion-icon :icon="imagesOutline" />
      <ion-label>Historias</ion-label>
    </ion-tab-button>

    <ion-tab-button tab="calendario" href="/calendario" class="custom-tab-button">
      <ion-icon :icon="calendarOutline" />
      <ion-label>Calendario</ion-label>
    </ion-tab-button>

    <ion-tab-button tab="perfil" href="/perfil" class="custom-tab-button">
      <ion-icon :icon="personOutline" />
      <ion-label>Perfil</ion-label>
    </ion-tab-button>
  </ion-tab-bar>
</template>

<script setup lang="ts">
import {
  IonTabBar,
  IonTabButton,
  IonIcon,
  IonLabel
} from '@ionic/vue';

import {
  chatbubblesOutline,
  peopleOutline,
  imagesOutline,
  calendarOutline,
  personOutline
} from 'ionicons/icons';
</script>

<style scoped>
/* ===== BARRA INFERIOR CYBER-GLASS ===== */
.custom-tab-bar {
  --background: rgba(14, 20, 32, 0.85);
  --border: none;
  background: rgba(14, 20, 32, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.5);
  height: 62px;
  padding-bottom: env(safe-area-inset-bottom);
}

/* ===== BOTONES DE NAVEGACIÓN ===== */
.custom-tab-button {
  --background: transparent;
  --color: #64748b;
  --color-selected: var(--app-accent, #10b981);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
}

/* Ícono */
.custom-tab-button ion-icon {
  font-size: 22px;
  transition: transform 0.25s ease, color 0.25s ease, filter 0.25s ease;
}

/* Etiqueta de texto */
.custom-tab-button ion-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.2px;
  margin-top: 3px;
  transition: color 0.25s ease;
}

/* ===== PESTAÑA ACTIVA (SELECCIONADA) ===== */
.custom-tab-button.tab-selected {
  --color: var(--app-accent, #10b981);
}

.custom-tab-button.tab-selected ion-icon {
  transform: translateY(-2px) scale(1.15);
  color: var(--app-accent, #10b981);
  filter: drop-shadow(0 0 8px rgba(16, 185, 129, 0.6));
}

.custom-tab-button.tab-selected ion-label {
  color: #34d399;
  font-weight: 700;
}

/* Línea neón indicadora superior */
.custom-tab-button.tab-selected::before {
  content: '';
  position: absolute;
  top: 0;
  width: 26px;
  height: 3px;
  background: linear-gradient(90deg, var(--app-accent, #10b981) 0%, var(--app-accent-secondary, #06b6d4) 100%);
  border-radius: 0 0 4px 4px;
  box-shadow: 0 2px 10px rgba(16, 185, 129, 0.8);
}
</style>