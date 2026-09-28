import api from '@/api'

export const programApi = {
  // ── Programme public ───────────────────────────────────────────────────────
  /** Infos publiques : niveaux, avantages, description */
  getProgram:   ()       => api.get('/program'),

  // ── Membership (utilisateur connecté) ─────────────────────────────────────
  /** Statut du membre : niveau, points, historique */
  getMembership: ()      => api.get('/program/membership'),
  /** Rejoindre le programme */
  join:          ()      => api.post('/program/join'),

  // ── Consultations conseiller ───────────────────────────────────────────────
  /** Créer une demande de consultation */
  requestConsultation: (payload) => api.post('/program/consultations', payload),
  /** Mes demandes de consultation */
  getMyConsultations:  ()        => api.get('/program/consultations'),
  /** Annuler une demande */
  cancelConsultation:  (id)      => api.delete(`/program/consultations/${id}`),

  // ── Suivi beauté (routine tracker) ────────────────────────────────────────
  /** Récupérer le suivi de routine du membre */
  getTracker:    ()            => api.get('/program/tracker'),
  /** Enregistrer un check-in de routine */
  checkin:       (payload)     => api.post('/program/tracker/checkin', payload),
}
