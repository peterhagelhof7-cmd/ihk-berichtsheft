<script setup lang="ts">
import { ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import Wochenansicht from './views/Wochenansicht.vue'
import Pruefung from './views/Pruefung.vue'

const istAusbilder = loadState<boolean>('berichtsheft', 'istAusbilder', false)
const istAzubi = loadState<boolean>('berichtsheft', 'istAzubi', false)

// Ist jemand beides (Ausbilder, der selbst auch Azubi ist), startet die
// Ansicht bei der eigenen Wochenansicht - die Pruefung fremder Wochen ist
// bewusst ein separater Menuepunkt, keine Vermischung in einer Ansicht.
const aktuelleAnsicht = ref<'wochenansicht' | 'pruefung'>(istAzubi ? 'wochenansicht' : 'pruefung')
</script>

<template>
	<NcContent app-name="berichtsheft">
		<NcAppNavigation v-if="istAusbilder && istAzubi">
			<NcAppNavigationItem
				name="Meine Wochenansicht"
				:active="aktuelleAnsicht === 'wochenansicht'"
				@click="aktuelleAnsicht = 'wochenansicht'" />
			<NcAppNavigationItem
				name="Prüfung (alle Azubis)"
				:active="aktuelleAnsicht === 'pruefung'"
				@click="aktuelleAnsicht = 'pruefung'" />
		</NcAppNavigation>
		<NcAppContent>
			<Wochenansicht v-if="istAzubi && aktuelleAnsicht === 'wochenansicht'" />
			<Pruefung v-else-if="istAusbilder" />
			<p v-else class="kein-zugriff">
				Dieser Nextcloud-Account ist weder als Azubi noch als Ausbilder im Berichtsheft aktiviert.
			</p>
		</NcAppContent>
	</NcContent>
</template>

<style scoped>
.kein-zugriff {
	margin: 32px;
	color: var(--color-text-maxcontrast);
}
</style>
