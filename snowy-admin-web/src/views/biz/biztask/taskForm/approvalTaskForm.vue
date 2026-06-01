<template>
	<a-comment>
		<template #avatar>
			<a-avatar :src="userInfo.avatar" :alt="userInfo.name" />
		</template>
		<template #content>
			<a-form-item>
				<a-textarea v-model:value="form.comment" placeholder="审批意见" :rows="4" />
			</a-form-item>
			<a-form-item>
				<a-space>
					<a-button v-if="hasPerm([premKey])" :loading="submitting" type="primary" @click="submit(true)">
						同意
					</a-button>
					<a-button @click="submit(false)" :loading="submitting" danger type="primary"> 拒绝 </a-button>
				</a-space>
			</a-form-item>
		</template>
	</a-comment>
</template>
<script setup lang="js">
	import { computed } from 'vue'
	import tool from '@/utils/tool'
	import bizTaskApi from '@/api/biz/bizTaskApi'
	import { cloneDeep } from 'lodash-es'

	const userInfo = tool.data.get('USER_INFO')
	const submitting = ref(false)
	const props = defineProps({
		taskDetail: {
			type: Object,
			required: true
		}
	})
	const emit = defineEmits({ successful: null })

	const premKey = computed(() => {
		const { processKey, category } = props.taskDetail
		return processKey + '-' + category
	})

	const form = ref({
		comment: '',
		approval: ''
	})
	const submit = async (flag) => {
		submitting.value = true

		form.value.approval = flag
		try {
			await bizTaskApi.approve({
				id: props.taskDetail.taskId,
				form: cloneDeep(form.value)
			})
			emit('successful')
		} catch (e) {
			console.error(e)
		} finally {
			submitting.value = false
		}
	}
</script>

<style scoped></style>
