<template>
	<a-card
		class="xn-wd"
		:bordered="false"
		:tab-list="tabListNoTitle"
		:active-tab-key="noTitleKey"
		@tabChange="(key) => onTabChange(key, 'noTitleKey')"
	>
		<p v-if="noTitleKey === 'sysConfig'">
			<SysConfig />
		</p>
	</a-card>
</template>

<script setup name="sysConfig">
	import SysConfig from './processConfig/index.vue'

	const key = ref('sysConfig')
	const noTitleKey = ref('sysConfig')

	const tabListNoTitle = [
		{
			key: 'processConfig',
			tab: '流程配置'
		}
	]

	const onTabChange = (value, type) => {
		if (type === 'key') {
			key.value = value
		} else if (type === 'noTitleKey') {
			noTitleKey.value = value
		}
	}
</script>
