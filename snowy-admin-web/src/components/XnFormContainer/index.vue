<template>
	<a-modal v-if="isModal" :open="visible" @cancel="cancel" v-bind="$attrs">
		<template v-for="slotKey in slotKeys" #[slotKey]>
			<slot :name="slotKey" />
		</template>
	</a-modal>
	<a-drawer
		:class="props.class"
		:maskClosable="false"
		v-else
		:open="visible"
		v-bind="$attrs"
		@close="cancel"
		:footer-style="{ textAlign: 'right' }"
	>
		<template v-for="slotKey in slotKeys" #[slotKey]>
			<slot :name="slotKey" />
		</template>
	</a-drawer>
</template>

<script setup>
	import { useSlots } from 'vue'
	import { globalStore } from '@/store'

	const slots = useSlots()
	const store = globalStore()
	const props = defineProps({
		class: {
			type: String,
			default: '',
			required: false
		},
		isUseModal: {
			type: Boolean,
			default: false,
			required: false
		},
		visible: {
			type: Boolean,
			default: false,
			required: false
		}
	})
	const FormContainerTypeEnum = {
		DRAWER: 'drawer',
		MODAL: 'modal'
	}
	const formStyle = computed(() => {
		return store.formStyle
	})
	const slotKeys = computed(() => {
		return Object.keys(slots)
	})
	const isModal = computed(() => {
		return FormContainerTypeEnum.MODAL === formStyle.value || props.isUseModal
	})
	const emit = defineEmits(['close'])
	const cancel = () => {
		emit('close')
	}
</script>
<script>
	// 声明额外的选项
	export default {
		inheritAttrs: false
	}
</script>
