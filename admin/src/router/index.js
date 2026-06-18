import { createRouter, createWebHashHistory } from 'vue-router'
import { getToken } from '../api/auth'

const routes = [
  { path: '/login', name: 'login', component: () => import('../views/Login.vue'), meta: { public: true } },
  { path: '/', name: 'forms', component: () => import('../views/FormsList.vue') },
  { path: '/forms/new', name: 'form-new', component: () => import('../views/FormBuilder.vue') },
  { path: '/forms/:id', name: 'form-edit', component: () => import('../views/FormBuilder.vue'), props: true },
  { path: '/forms/:id/submissions', name: 'submissions', component: () => import('../views/Submissions.vue'), props: true },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

// Guard: blocca le rotte private se non autenticati.
router.beforeEach((to) => {
  if (!to.meta.public && !getToken()) {
    return { name: 'login' }
  }
  if (to.name === 'login' && getToken()) {
    return { name: 'forms' }
  }
})

export default router
