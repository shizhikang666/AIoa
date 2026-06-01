<script name="reportError" setup lang="js">
	const emit = defineEmits(['reload'])
</script>

<template>
	<a-result title="500" sub-title="Sorry, the server is wrong.">
		<template #icon>
			<ExclamationCircleFilled />
		</template>
		<template #extra>
			<a-button type="primary" @click="emit('reload')">重新加载</a-button>
		</template>
	</a-result>
</template>

<style scoped></style>
