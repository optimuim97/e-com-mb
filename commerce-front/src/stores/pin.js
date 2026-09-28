import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/api'

export const usePinStore = defineStore('pin', () => {
  const verified = ref(sessionStorage.getItem('pin_verified') === '1')

  async function verify(pin) {
    await api.post('/auth/pin/verify', { pin })
    verified.value = true
    sessionStorage.setItem('pin_verified', '1')
  }

  async function setPin(pin, currentPin = null) {
    const payload = { pin }
    if (currentPin) payload.current_pin = currentPin
    await api.post('/auth/pin/set', payload)
  }

  function reset() {
    verified.value = false
    sessionStorage.removeItem('pin_verified')
  }

  return { verified, verify, setPin, reset }
})
