import { createRouter, createWebHistory } from 'vue-router'
import RegistrationForm from './views/RegistrationForm.vue'
import ConfirmationView from './views/ConfirmationView.vue'
import CompleteView from './views/CompleteView.vue'
import InvalidInvitation from './views/InvalidInvitation.vue'
import AdminExportView from './views/AdminExportView.vue'
import AdminEventsView from './views/AdminEventsView.vue'
import AdminEventFormView from './views/AdminEventFormView.vue'
import AdminEventDetailView from './views/AdminEventDetailView.vue'
import AdminPendingMembersView from './views/AdminPendingMembersView.vue'
import AdminCompletedMembersView from './views/AdminCompletedMembersView.vue'
import AdminLoginView from './views/AdminLoginView.vue'
import { ensureAdminSession } from './adminAuth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: InvalidInvitation, meta: { step: 0 } },
    { path: '/register/:token', name: 'registration', component: RegistrationForm, meta: { step: 1 } },
    { path: '/register/:token/confirm', name: 'confirmation', component: ConfirmationView, meta: { step: 2 } },
    { path: '/register/:token/complete', name: 'complete', component: CompleteView, meta: { step: 3 } },
    { path: '/admin/login', name: 'admin-login', component: AdminLoginView, meta: { step: 0 } },
    { path: '/admin/export', name: 'admin-export', component: AdminExportView, meta: { step: 0, requiresAdmin: true } },
    { path: '/admin/events', name: 'admin-events', component: AdminEventsView, meta: { step: 0, requiresAdmin: true } },
    { path: '/admin/events/new', name: 'admin-event-new', component: AdminEventFormView, meta: { step: 0, requiresAdmin: true } },
    { path: '/admin/events/:eventId', name: 'admin-event-detail', component: AdminEventDetailView, meta: { step: 0, requiresAdmin: true } },
    { path: '/admin/events/:eventId/edit', name: 'admin-event-edit', component: AdminEventFormView, meta: { step: 0, requiresAdmin: true } },
    { path: '/admin/events/:eventId/pending', name: 'admin-event-pending', component: AdminPendingMembersView, meta: { step: 0, requiresAdmin: true } },
    { path: '/admin/events/:eventId/members', name: 'admin-event-members', component: AdminCompletedMembersView, meta: { step: 0, requiresAdmin: true } },
    { path: '/:pathMatch(.*)*', component: InvalidInvitation, meta: { step: 0 } },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  if (to.meta.requiresAdmin && !(await ensureAdminSession())) {
    return { name: 'admin-login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'admin-login' && await ensureAdminSession()) {
    return { name: 'admin-events' }
  }
  return true
})

export default router
