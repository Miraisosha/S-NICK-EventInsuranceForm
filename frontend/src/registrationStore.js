import { reactive } from 'vue'

const emptyForm = () => ({
  event_id: '',
  full_name: '',
  full_name_kana: '',
  email: '',
  phone: '',
  postal_code: '',
  prefecture: '',
  city: '',
  street_address: '',
  building: '',
  birth_date: '',
  privacy_consent: false,
})

export const registrationStore = reactive({
  token: '',
  invitedName: '',
  policyVersion: '',
  events: [],
  form: emptyForm(),
  validated: false,
  completed: false,
  reset(token = '') {
    this.token = token
    this.invitedName = ''
    this.policyVersion = ''
    this.events = []
    this.form = emptyForm()
    this.validated = false
    this.completed = false
  },
})
