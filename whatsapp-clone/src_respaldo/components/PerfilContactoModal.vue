<template>
  <ion-modal
    :is-open="visible"
    class="perfil-contacto-modal"
    :initial-breakpoint="1"
    :breakpoints="[0, 1]"
    :handle="true"
    @didDismiss="emit('cerrar')"
  >
    <ion-content class="perfil-contacto-content">
      <div class="perfil-contacto-panel">
        <ion-button
          class="cerrar-perfil"
          fill="clear"
          aria-label="Cerrar perfil"
          @click="emit('cerrar')"
        >
          <ion-icon :icon="closeOutline" />
        </ion-button>

        <div class="avatar-perfil-wrap">
          <ion-avatar class="avatar-perfil">
            <img v-if="perfil?.foto" :src="perfil.foto" :alt="perfil.nombre" />
          </ion-avatar>
        </div>

        <h1>{{ perfil?.nombre || "Perfil" }}</h1>
        <p v-if="perfil?.en_linea" class="presencia en-linea">En línea</p>
        <p v-else-if="perfil?.ultima_conexion" class="presencia">
          Últ. vez {{ formatearUltimaConexion(perfil.ultima_conexion) }}
        </p>
        <ion-badge v-if="perfil?.rol === 'administrador'" class="badge-rol">
          Administrador del grupo
        </ion-badge>

        <section class="acerca-de">
          <span>INFO.</span>
          <p>{{ perfil?.descripcion || "Sin información" }}</p>
        </section>

        <section v-if="perfil?.telefono" class="acerca-de dato-contacto">
          <span>TELÉFONO</span>
          <p>{{ perfil.telefono }}</p>
        </section>

        <ion-button
          v-if="perfil?.telefono"
          class="privacidad-contacto"
          fill="outline"
          @click="configurarPrivacidad"
        >
          Configurar privacidad para este contacto
        </ion-button>
      </div>
    </ion-content>
  </ion-modal>
</template>

<script setup lang="ts">
import {
  IonModal,
  IonContent,
  IonAvatar,
  IonButton,
  IonIcon,
  IonBadge,
  alertController,
} from "@ionic/vue";
import { closeOutline } from "ionicons/icons";

interface PerfilContacto {
  id: number;
  nombre: string;
  foto: string | null;
  descripcion?: string | null;
  telefono?: string | null;
  ultima_conexion?: string | null;
  en_linea?: boolean;
  rol?: string | null;
}

const props = defineProps<{
  visible: boolean;
  perfil: PerfilContacto | null;
}>();

const emit = defineEmits<{ cerrar: [] }>();

const configurarPrivacidad = async () => {
  const token = localStorage.getItem("token") || "";
  const contactoId = props.perfil?.id;
  if (!token || !contactoId) return;

  try {
    const consultar = new URLSearchParams({ token, contacto_id: String(contactoId) });
    const respuesta = await fetch(
      "http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=obtenerRestriccionesContacto",
      { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: consultar.toString(), cache: "no-store" },
    );
    const datos = await respuesta.json();
    if (!respuesta.ok || !datos.ok) throw new Error(datos.error || "No se pudo cargar la privacidad.");

    const actual = datos.restricciones || {};
    const alerta = await alertController.create({
      mode: "ios",
      header: "Privacidad para este contacto",
      message: `Elige qué no podrá ver ${props.perfil?.nombre || "este contacto"}.`,
      inputs: [
        { type: "checkbox", label: "Mis historias", value: "estados", checked: Boolean(Number(actual.ocultar_estados)) },
        { type: "checkbox", label: "Mi foto de perfil", value: "foto", checked: Boolean(Number(actual.ocultar_foto)) },
        { type: "checkbox", label: "Mi información", value: "descripcion", checked: Boolean(Number(actual.ocultar_descripcion)) },
        { type: "checkbox", label: "Mi última conexión", value: "ultima_conexion", checked: Boolean(Number(actual.ocultar_ultima_conexion)) },
      ],
      buttons: [
        { text: "Cancelar", role: "cancel" },
        {
          text: "Guardar",
          role: "confirm",
          handler: async (seleccion: string[]) => {
            try {
              const datosGuardar = new URLSearchParams({
                token,
                contacto_id: String(contactoId),
                ocultar_estados: seleccion.includes("estados") ? "1" : "0",
                ocultar_foto: seleccion.includes("foto") ? "1" : "0",
                ocultar_descripcion: seleccion.includes("descripcion") ? "1" : "0",
                ocultar_ultima_conexion: seleccion.includes("ultima_conexion") ? "1" : "0",
              });
              const guardar = await fetch(
                "http://localhost/new%20app/api/controladores/usuarios_controlador.php?task=guardarRestriccionesContacto",
                { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: datosGuardar.toString() },
              );
              const resultado = await guardar.json();
              if (!guardar.ok || !resultado.ok) throw new Error(resultado.error || "No se pudo guardar.");
            } catch (error) {
              const aviso = await alertController.create({
                mode: "ios",
                header: "No se pudo guardar",
                message: error instanceof Error ? error.message : "Inténtalo de nuevo.",
                buttons: ["OK"],
              });
              await aviso.present();
            }
          },
        },
      ],
    });
    await alerta.present();
  } catch (error) {
    const alerta = await alertController.create({
      mode: "ios",
      header: "No se pudo guardar",
      message: error instanceof Error ? error.message : "Inténtalo de nuevo.",
      buttons: ["OK"],
    });
    await alerta.present();
  }
};

const formatearUltimaConexion = (valor: string) => {
  const fecha = new Date(valor.replace(" ", "T"));
  if (Number.isNaN(fecha.getTime())) return valor;
  return new Intl.DateTimeFormat("es-MX", { dateStyle: "short", timeStyle: "short" }).format(fecha);
};
</script>

<style scoped>
:global(ion-modal.perfil-contacto-modal) {
  --height: 100%;
  --width: 100%;
  --border-radius: 0;
}

.perfil-contacto-content {
  --background: #0b1120;
}

.perfil-contacto-panel {
  position: relative;
  min-height: 100%;
  padding: 34px 24px 38px;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #f8fafc;
  background:
    radial-gradient(circle at 50% 0, rgba(16, 185, 129, 0.17), transparent 42%),
    #0b1120;
}

.cerrar-perfil {
  position: absolute;
  top: 6px;
  right: 10px;
  --color: #cbd5e1;
}

.avatar-perfil-wrap {
  width: 112px;
  height: 112px;
  padding: 4px;
  margin: 12px 0 16px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--app-accent, #10b981), var(--app-accent-secondary, #06b6d4));
  box-shadow: 0 0 30px rgba(16, 185, 129, 0.2);
}

.avatar-perfil {
  width: 100%;
  height: 100%;
  background: #111827;
  border: 3px solid #0b1120;
}

.avatar-perfil img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

h1 {
  margin: 0;
  color: #fff;
  font-size: 23px;
  font-weight: 750;
  text-align: center;
}

.presencia {
  margin: 6px 0 0;
  color: #94a3b8;
  font-size: 13px;
}

.presencia.en-linea {
  color: #34d399;
}

.badge-rol {
  margin-top: 10px;
  --background: rgba(16, 185, 129, 0.16);
  --color: #6ee7b7;
  border: 1px solid rgba(16, 185, 129, 0.28);
}

.acerca-de {
  width: min(100%, 420px);
  margin-top: 28px;
  padding: 16px;
  background: rgba(255, 255, 255, 0.055);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 16px;
}

.acerca-de span {
  color: #94a3b8;
  font-size: 10px;
  font-weight: 750;
  letter-spacing: 1px;
}

.acerca-de p {
  margin: 7px 0 0;
  color: #e2e8f0;
  font-size: 15px;
  line-height: 1.45;
  white-space: pre-wrap;
}

.privacidad-contacto {
  width: min(100%, 420px);
  margin-top: 24px;
  --border-color: rgba(148, 163, 184, 0.3);
  --color: #cbd5e1;
  text-transform: none;
}
</style>
