import { createApp } from 'vue'
import AdminSettings from './views/AdminSettings.vue'
import PersoenlicheAngaben from './views/PersoenlicheAngaben.vue'

const adminEl = document.getElementById('berichtsheft-admin-settings')
if (adminEl) {
	createApp(AdminSettings).mount(adminEl)
}

const personalEl = document.getElementById('berichtsheft-personal-settings')
if (personalEl) {
	createApp(PersoenlicheAngaben).mount(personalEl)
}
