import api from '@/api'

export const adminProgramApi = {
  // ── Statistiques ───────────────────────────────────────────────────────────
  getStats:    ()       => api.get('/admin/program/stats'),

  // ── Membres ───────────────────────────────────────────────────────────────
  getMembers:  (params) => api.get('/admin/program/members', { params }),
  getMember:   (id)     => api.get(`/admin/program/members/${id}`),
  updateMember:(id, payload) => api.patch(`/admin/program/members/${id}`, payload),

  // ── Niveaux (tiers) ────────────────────────────────────────────────────────
  getTiers:    ()            => api.get('/admin/program/tiers'),
  updateTier:  (id, payload) => api.patch(`/admin/program/tiers/${id}`, payload),

  // ── Avantages (benefits) ───────────────────────────────────────────────────
  getBenefits:   ()            => api.get('/admin/program/benefits'),
  createBenefit: (payload)     => api.post('/admin/program/benefits', payload),
  updateBenefit: (id, payload) => api.patch(`/admin/program/benefits/${id}`, payload),
  deleteBenefit: (id)          => api.delete(`/admin/program/benefits/${id}`),

  // ── Consultations ──────────────────────────────────────────────────────────
  getConsultations: (params)     => api.get('/admin/program/consultations', { params }),
  updateConsultation:(id, payload)=> api.patch(`/admin/program/consultations/${id}`, payload),
}
