<template>
  <ion-page class="perfil-page">
    <ion-header class="glass-header">
      <ion-toolbar> <ion-title>Perfil</ion-title> </ion-toolbar>
    </ion-header>
    <ion-content :fullscreen="true">
      <div class="glow-emerald"></div>
      <div class="glow-cyan"></div>

      <div class="contenido">
        <div class="perfil-card">
          <div class="foto-container">
            <div class="foto-ring">
              <ion-avatar class="foto-perfil">
                <img :src="usuario.avatar" />
              </ion-avatar>
            </div>

            <ion-button class="boton-camara" shape="round">
              <ion-icon :icon="cameraOutline" />
            </ion-button>
          </div>

          <h1>{{ usuario.nombre }}</h1>
          <p class="telefono">{{ usuario.telefono }}</p>

          <ion-button fill="outline" shape="round" class="boton-editar">
            <ion-icon slot="start" :icon="createOutline" />
            Editar perfil
          </ion-button>
        </div>

        <div class="seccion">
          <span class="seccion-titulo">CUENTA</span>

          <ion-list class="lista-opciones">
            <ion-item button lines="none" class="opcion">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="notificationsOutline" />
              </div>

              <ion-label>
                <h2>Notificaciones</h2>
                <p>Sonidos, alertas y mensajes</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>

            <ion-item button lines="none" class="opcion">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="lockClosedOutline" />
              </div>

              <ion-label>
                <h2>Privacidad</h2>
                <p>Controla quién puede contactarte</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>

            <ion-item button lines="none" class="opcion">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="colorPaletteOutline" />
              </div>

              <ion-label>
                <h2>Apariencia</h2>
                <p>Tema y colores de la aplicación</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>

            <ion-item button lines="none" class="opcion">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="cloudOutline" />
              </div>

              <ion-label>
                <h2>Almacenamiento</h2>
                <p>Archivos, imágenes y documentos</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>

            <ion-item button lines="none" class="opcion">
              <div slot="start" class="icono-opcion">
                <ion-icon :icon="informationCircleOutline" />
              </div>

              <ion-label>
                <h2>Acerca de</h2>
                <p>Información de la aplicación</p>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>
          </ion-list>
        </div>

        <div class="seccion">
          <span class="seccion-titulo">SESIÓN</span>

          <ion-list class="lista-opciones">
            <ion-item button lines="none" class="opcion cerrar-sesion">
              <div slot="start" class="icono-opcion icono-salida">
                <ion-icon :icon="logOutOutline" />
              </div>

              <ion-label>
                <h2>Cerrar sesión</h2>
              </ion-label>

              <ion-icon
                slot="end"
                class="flecha"
                :icon="chevronForwardOutline"
              />
            </ion-item>
          </ion-list>
        </div>

        <div class="version">MiApp v1.0.0</div>
      </div>
    </ion-content>
  </ion-page>
</template>
<script setup lang="ts">
import { onMounted, ref } from "vue";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonAvatar,
  IonButton,
  IonIcon,
  IonList,
  IonItem,
  IonLabel,
} from "@ionic/vue";
import {
  cameraOutline,
  createOutline,
  notificationsOutline,
  lockClosedOutline,
  colorPaletteOutline,
  cloudOutline,
  informationCircleOutline,
  logOutOutline,
  chevronForwardOutline,
} from "ionicons/icons";
interface Usuario {
  id: number;
  telefono: string;
  nombre: string;
  apellido: string;
  foto: string | null;
}
const usuario = ref({
  nombre: "",
  telefono: "",
  avatar: "https://i.pravatar.cc/300?img=12",
});
const cargarUsuario = () => {
  const usuarioGuardado = localStorage.getItem("usuario");
  if (!usuarioGuardado) {
    return;
  }
  const usuarioApi: Usuario = JSON.parse(usuarioGuardado);
  usuario.value = {
    nombre: `${usuarioApi.nombre} ${usuarioApi.apellido}`,
    telefono: usuarioApi.telefono,
    avatar: usuarioApi.foto || "https://i.pravatar.cc/300?img=12",
  };
};
onMounted(() => {
  cargarUsuario();
});
</script>
<style scoped>
.perfil-page {
  --ion-background-color: #090d16;
  background: #090d16;
}
.glass-header {
  background: transparent;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  position: relative;
  z-index: 20;
}
.glass-header ion-toolbar {
  --background: rgba(9, 13, 22, 0.72);
  --border-width: 0;
}
.glass-header ion-title {
  color: #ffffff;
  font-size: 24px;
  font-weight: 700;
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
  padding: 10px 14px 100px;
}
.glow-emerald,
.glow-cyan {
  position: fixed;
  width: 280px;
  height: 280px;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.17;
  pointer-events: none;
  z-index: 0;
}
.glow-emerald {
  top: 50px;
  left: -140px;
  background: #10b981;
}
.glow-cyan {
  top: 400px;
  right: -150px;
  background: #06b6d4;
}
.perfil-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 26px 20px 24px;
  text-align: center;
  background: rgba(255, 255, 255, 0.045);
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 24px;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
}
.foto-container {
  position: relative;
  margin-bottom: 16px;
}
.foto-ring {
  width: 116px;
  height: 116px;
  padding: 3px;
  border-radius: 50%;
  background: linear-gradient(135deg, #10b981, #06b6d4);
  box-shadow:
    0 0 25px rgba(16, 185, 129, 0.2),
    0 0 45px rgba(6, 182, 212, 0.08);
}
.foto-perfil {
  width: 110px;
  height: 110px;
  background: #090d16;
}
.foto-perfil img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  border: 3px solid #090d16;
}
.boton-camara {
  position: absolute;
  right: -4px;
  bottom: -3px;
  width: 42px;
  height: 42px;
  margin: 0;
  --background: linear-gradient(135deg, #10b981, #06b6d4);
  --color: #ffffff;
  --box-shadow: 0 0 16px rgba(16, 185, 129, 0.28);
}
.boton-camara ion-icon {
  font-size: 20px;
}
.perfil-card h1 {
  margin: 4px 0 5px;
  color: #ffffff;
  font-size: 24px;
  font-weight: 700;
}
.telefono {
  margin: 0 0 17px;
  color: #8993a5;
  font-size: 14px;
}
.boton-editar {
  --border-color: rgba(16, 185, 129, 0.45);
  --color: #10b981;
  --background: rgba(16, 185, 129, 0.06);
  --background-hover: rgba(16, 185, 129, 0.12);
  --border-radius: 18px;
  font-weight: 600;
  text-transform: none;
}
.seccion {
  margin-top: 24px;
}
.seccion-titulo {
  display: block;
  margin: 0 5px 10px;
  color: #10b981;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1.2px;
}
.lista-opciones {
  margin: 0;
  padding: 0;
  background: transparent;
}
.opcion {
  --background: rgba(255, 255, 255, 0.045);
  --background-activated: rgba(255, 255, 255, 0.08);
  --color: #ffffff;
  --inner-border-width: 0;
  --padding-start: 12px;
  --padding-end: 12px;
  --min-height: 72px;
  margin-bottom: 8px;
  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 18px;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}
.icono-opcion {
  width: 40px;
  height: 40px;
  margin-right: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 13px;
  background: rgba(16, 185, 129, 0.09);
  border: 1px solid rgba(16, 185, 129, 0.14);
  color: #10b981;
}
.icono-opcion ion-icon {
  font-size: 21px;
}
.opcion ion-label h2 {
  margin: 0 0 4px;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
}
.opcion ion-label p {
  margin: 0;
  color: #8993a5;
  font-size: 12px;
}
.flecha {
  color: #596273;
  font-size: 18px;
}
.cerrar-sesion {
  --background: rgba(239, 68, 68, 0.045);
  border-color: rgba(239, 68, 68, 0.08);
}
.cerrar-sesion .icono-opcion {
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}
.cerrar-sesion ion-label h2 {
  color: #ef4444;
}
.cerrar-sesion .flecha {
  color: rgba(239, 68, 68, 0.55);
}
.version {
  text-align: center;
  padding: 20px 0 20px;
  color: #596273;
  font-size: 11px;
}
</style>
