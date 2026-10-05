import { createRouter, createWebHistory } from '@ionic/vue-router';
import { RouteRecordRaw } from 'vue-router';

import HomePage from '../views/HomePage.vue';
import ChatsPage from '../views/ChatsPage.vue';
import GruposPage from '../views/GruposPage.vue';
import HistoriasPage from '../views/HistoriasPage.vue';
import CalendarioPage from '../views/CalendarioPage.vue';
import PerfilPage from '../views/PerfilPage.vue';
import ChatPage from '../views/ChatPage.vue';
import GrupoChatPage from '../views/GrupoChatPage.vue';
import HistoriaPage from '../views/HistoriaPage.vue';
import RegistroPage from '@/views/RegistroPage.vue';
import LoginPage from '@/views/LoginPage.vue';
import AgregarContactoPage from '@/views/AgregarContactoPage.vue';

const routes: Array<RouteRecordRaw> = [
{
path: '/',
redirect: () => (localStorage.getItem('token') ? '/chats' : '/login')
},
{
  path: '/agregar-contacto',
  name: 'AgregarContacto',
  component: AgregarContactoPage
},
{
path: '/login',
name: 'Login',
component: LoginPage
},
{
path: '/registro',
component: RegistroPage
},
{
path: '/chat/:id',
name: 'Chat',
component: ChatPage
},
{
path: '/home',
name: 'Home',
component: HomePage
},
{
path: '/chats',
name: 'Chats',
component: ChatsPage
},
{
path: '/grupos',
name: 'Grupos',
component: GruposPage
},
{
path: '/grupo/:id',
name: 'GrupoChat',
component: GrupoChatPage
},
{
path: '/historias',
name: 'Historias',
component: HistoriasPage
},
{
path: '/historia/:id',
name: 'Historia',
component: HistoriaPage
},
{
path: '/calendario',
name: 'Calendario',
component: CalendarioPage
},
{
path: '/perfil',
name: 'Perfil',
component: PerfilPage
}
];

const router = createRouter({
history: createWebHistory(import.meta.env.BASE_URL),
routes
});

router.beforeEach((to) => {
  const token = localStorage.getItem('token');
  const esPublica = to.name === 'Login' || to.path === '/registro';

  if (!token && !esPublica) return '/login';
  if (token && to.name === 'Login') return '/chats';
  return true;
});

export default router;
