import { createRouter, createWebHistory } from 'vue-router'
import RegistrationForm from './views/RegistrationForm.vue'
import ConfirmationView from './views/ConfirmationView.vue'
import CompleteView from './views/CompleteView.vue'
import InvalidInvitation from './views/InvalidInvitation.vue'
import AdminExportView from './views/AdminExportView.vue'
import AdminEventsView from './views/AdminEventsView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: InvalidInvitation, meta: { step: 0 } },
    { path: '/register/:token', name: 'registration', component: RegistrationForm, meta: { step: 1 } },
    { path: '/register/:token/confirm', name: 'confirmation', component: ConfirmationView, meta: { step: 2 } },
    { path: '/register/:token/complete', name: 'complete', component: CompleteView, meta: { step: 3 } },
    { path: '/admin/export', name: 'admin-export', component: AdminExportView, meta: { step: 0 } },
    { path: '/admin/events', name: 'admin-events', component: AdminEventsView, meta: { step: 0 } },
    { path: '/:pathMatch(.*)*', component: InvalidInvitation, meta: { step: 0 } },
  ],
  scrollBehavior: () => ({ top: 0 }),
})
