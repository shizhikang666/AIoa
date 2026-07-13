<template>
	<a-modal
		v-model:open="visible"
		title="迁移公司/组织"
		width="920px"
		:confirm-loading="executeLoading"
		:ok-button-props="{ disabled: !previewData?.previewHash || previewLoading }"
		ok-text="执行迁移"
		cancel-text="关闭"
		@ok="executeMigration"
		@cancel="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-row :gutter="16">
				<a-col :span="8">
					<a-form-item label="用户">
						<a-input :value="sourceUserText" disabled />
					</a-form-item>
				</a-col>
				<a-col :span="8">
					<a-form-item label="当前组织">
						<a-input :value="sourceOrgText" disabled />
					</a-form-item>
				</a-col>
				<a-col :span="8">
					<a-form-item label="当前岗位">
						<a-input :value="sourcePositionText" disabled />
					</a-form-item>
				</a-col>
			</a-row>
			<a-row :gutter="16">
				<a-col :span="12">
					<a-form-item label="目标组织" name="targetOrgId">
						<a-tree-select
							v-model:value="formData.targetOrgId"
							:tree-data="orgTreeData"
							:field-names="treeFieldNames"
							:loading="orgLoading"
							allow-clear
							show-search
							tree-default-expand-all
							placeholder="请选择目标组织"
							@change="onTargetOrgChange"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="12">
					<a-form-item label="目标岗位" name="targetPositionId">
						<a-select
							v-model:value="formData.targetPositionId"
							:options="positionOptions"
							:loading="positionLoading"
							:disabled="!formData.targetOrgId"
							allow-clear
							show-search
							option-filter-prop="label"
							placeholder="请选择目标岗位"
							@change="clearPreview"
						/>
					</a-form-item>
				</a-col>
			</a-row>
			<a-space>
				<a-button type="primary" :loading="previewLoading" @click="previewMigration">Dry-run 预览</a-button>
				<a-button @click="clearPreview">清空预览</a-button>
			</a-space>
		</a-form>

		<a-alert
			v-if="previewData"
			class="migration-alert"
			type="warning"
			show-icon
			:message="previewMessage"
		/>

		<a-table
			v-if="previewData"
			size="small"
			:columns="tableColumns"
			:data-source="previewData.tables || []"
			:pagination="false"
			row-key="table"
		/>

		<a-table
			v-if="previewData?.skipped?.length"
			class="migration-skipped"
			size="small"
			:columns="skippedColumns"
			:data-source="previewData.skipped"
			:pagination="{ pageSize: 5 }"
			row-key="id"
		/>
	</a-modal>
</template>

<script setup name="sysUserMigrationForm">
	import { computed, ref } from 'vue'
	import { message, Modal } from 'ant-design-vue'
	import userApi from '@/api/sys/userApi'

	const emit = defineEmits({ successful: null })
	const visible = ref(false)
	const formRef = ref()
	const sourceRecord = ref({})
	const formData = ref({
		userId: '',
		targetOrgId: undefined,
		targetPositionId: undefined
	})
	const previewData = ref(null)
	const orgTreeData = ref([])
	const positionOptions = ref([])
	const orgLoading = ref(false)
	const positionLoading = ref(false)
	const previewLoading = ref(false)
	const executeLoading = ref(false)
	const treeFieldNames = { children: 'children', label: 'name', value: 'id' }

	const formRules = {
		targetOrgId: [{ required: true, message: '请选择目标组织' }],
		targetPositionId: [{ required: true, message: '请选择目标岗位' }]
	}

	const tableColumns = [
		{ title: '表名', dataIndex: 'table', ellipsis: true },
		{ title: '匹配行', dataIndex: 'matchedRows', width: 90 },
		{ title: '可迁移', dataIndex: 'affectedRows', width: 90 },
		{ title: '跳过', dataIndex: 'skippedRows', width: 80 },
		{ title: '不变', dataIndex: 'unchangedRows', width: 80 },
		{ title: '原因', dataIndex: 'reason', width: 180 }
	]

	const skippedColumns = [
		{ title: '表名', dataIndex: 'table', ellipsis: true },
		{ title: '记录ID', dataIndex: 'id', ellipsis: true },
		{ title: '原因', dataIndex: 'reason', width: 150 },
		{
			title: '组织字段',
			dataIndex: 'orgValues',
			customRender: ({ text }) => JSON.stringify(text || {})
		}
	]

	const sourceUserText = computed(() => {
		const account = sourceRecord.value.account || sourceRecord.value.ACCOUNT || ''
		const name = sourceRecord.value.name || sourceRecord.value.NAME || ''
		return [account, name].filter(Boolean).join(' / ')
	})
	const sourceOrgText = computed(() => sourceRecord.value.orgName || sourceRecord.value.ORG_NAME || sourceRecord.value.orgId || '')
	const sourcePositionText = computed(
		() => sourceRecord.value.positionName || sourceRecord.value.POSITION_NAME || sourceRecord.value.positionId || ''
	)
	const previewMessage = computed(() => {
		const summary = previewData.value?.summary || {}
		return `将迁移 ${summary.affectedRows || 0} 行业务记录，跳过 ${summary.skippedRows || 0} 行，不变 ${
			summary.unchangedRows || 0
		} 行。执行后该用户现有 token 会失效，需要重新登录。`
	})

	const onOpen = (record) => {
		sourceRecord.value = record || {}
		formData.value = {
			userId: record?.id || record?.ID || '',
			targetOrgId: undefined,
			targetPositionId: undefined
		}
		previewData.value = null
		positionOptions.value = []
		visible.value = true
		loadOrgTree()
	}

	const onClose = () => {
		visible.value = false
		previewData.value = null
		positionOptions.value = []
	}

	const loadOrgTree = () => {
		orgLoading.value = true
		userApi
			.userOrgTreeSelector()
			.then((res) => {
				orgTreeData.value = res || []
			})
			.finally(() => {
				orgLoading.value = false
			})
	}

	const onTargetOrgChange = (orgId) => {
		formData.value.targetPositionId = undefined
		previewData.value = null
		positionOptions.value = []
		if (orgId) {
			loadPositions(orgId)
		}
	}

	const loadPositions = (orgId) => {
		positionLoading.value = true
		userApi
			.userPositionSelector({ orgId, size: 100 })
			.then((res) => {
				const rows = Array.isArray(res) ? res : res?.records || []
				positionOptions.value = rows.map((item) => ({
					value: item.value || item.id,
					label: item.label || item.name || item.title || item.id
				}))
			})
			.finally(() => {
				positionLoading.value = false
			})
	}

	const clearPreview = () => {
		previewData.value = null
	}

	const previewMigration = () => {
		formRef.value.validate().then(() => {
			previewLoading.value = true
			userApi
				.userMigrationPreview({ ...formData.value })
				.then((res) => {
					previewData.value = res
					message.success('Dry-run 预览已生成')
				})
				.finally(() => {
					previewLoading.value = false
				})
		})
	}

	const executeMigration = () => {
		if (!previewData.value?.previewHash) {
			message.warning('请先执行 Dry-run 预览')
			return
		}

		Modal.confirm({
			title: '确认执行迁移',
			content: previewMessage.value,
			okText: '执行迁移',
			okType: 'danger',
			cancelText: '取消',
			onOk() {
				executeLoading.value = true
				return userApi
					.userMigrationExecute({
						...formData.value,
						previewHash: previewData.value.previewHash
					})
					.then(() => {
						message.success('迁移已完成')
						emit('successful')
						onClose()
					})
					.finally(() => {
						executeLoading.value = false
					})
			}
		})
	}

	defineExpose({
		onOpen
	})
</script>

<style scoped>
	.migration-alert {
		margin: 16px 0;
	}
	.migration-skipped {
		margin-top: 16px;
	}
</style>
