import { createApp } from 'vue'
import PersoenlicheAngaben from './views/PersoenlicheAngaben.vue'

const personalEl = document.getElementById('berichtsheft-personal-settings')
if (personalEl) {
	createApp(PersoenlicheAngaben).mount(personalEl)
}
