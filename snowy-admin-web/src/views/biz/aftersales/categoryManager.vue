<template>
	<a-modal v-model:open="open" title="售后分类管理" width="820px" :footer="null" destroy-on-close>
		<div class="category-toolbar">
			<a-button type="primary" @click="openForm()">
				<template #icon><plus-outlined /></template>
				新增分类
			</a-button>
		</div>
		<a-table
			:loading="loading"
			:data-source="rows"
			:columns="columns"
			row-key="id"
			size="middle"
			bordered
			:pagination="false"
		>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'status'">
					<a-tag :color="record.status === 'ENABLE' ? 'green' : 'default'">
						{{ record.status === 'ENABLE' ? '启用' : '停用' }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space v-if="record.canEdit">
						<a @click="openForm(record)">编辑</a>
						<a-popconfirm title="确定删除这个分类？" @confirm="deleteCategory(record)">
							<a-typography-link type="danger">删除</a-typography-link>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</a-table>
	</a-modal>

	<a-modal
		v-model:open="formOpen"
		:title="formData.id ? '编辑分类' : '新增分类'"
		:confirm-loading="saving"
		@ok="saveCategory"
	>
		<a-form ref="formRef" :model="formData" :rules="rules" layout="vertical">
			<a-form-item label="分类名称" name="name">
				<a-input v-model:value="formData.name" :maxlength="100" />
			</a-form-item>
			<a-row :gutter="16">
				<a-col :span="12">
					<a-form-item label="排序" name="sortCode">
						<a-input-number v-model:value="formData.sortCode" :min="0" :max="9999" style="width: 100%" />
					</a-form-item>
				</a-col>
				<a-col :span="12">
					<a-form-item label="状态" name="status">
						<a-select v-model:value="formData.status" :options="statusOptions" />
					</a-form-item>
				</a-col>
			</a-row>
			<a-form-item label="备注" name="remark">
				<a-textarea v-model:value="formData.remark" :maxlength="500" :rows="3" />
			</a-form-item>
		</a-form>
	</a-modal>
</template>

<script setup name="afterSalesCategoryManager">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import afterSalesApi from '@/api/biz/afterSalesApi'

	const emit = defineEmits({ changed: null })
	const open = ref(false)
	const formOpen = ref(false)
	const loading = ref(false)
	const saving = ref(false)
	const rows = ref([])
	const formRef = ref()
	const formData = ref({})
	const rules = { name: [required('请输入分类名称')] }
	const statusOptions = [
		{ label: '启用', value: 'ENABLE' },
		{ label: '停用', value: 'DISABLE' }
	]
	const columns = [
		{ title: '分类名称', dataIndex: 'name' },
		{ title: '排序', dataIndex: 'sortCode', width: 90 },
		{ title: '状态', dataIndex: 'status', width: 90 },
		{ title: '备注', dataIndex: 'remark', ellipsis: true },
		{ title: '操作', dataIndex: 'action', width: 130 }
	]

	const loadData = async () => {
		loading.value = true
		try {
			rows.value = await afterSalesApi.categoryList({ includeDisabled: true })
		} finally {
			loading.value = false
		}
	}

	const onOpen = async () => {
		open.value = true
		await loadData()
	}

	const openForm = (record) => {
		formData.value = record ? cloneDeep(record) : { name: '', sortCode: 100, status: 'ENABLE', remark: '' }
		formOpen.value = true
	}

	const saveCategory = async () => {
		await formRef.value.validate()
		saving.value = true
		try {
			await afterSalesApi.categorySubmit(cloneDeep(formData.value), Boolean(formData.value.id))
			formOpen.value = false
			await loadData()
			emit('changed')
		} finally {
			saving.value = false
		}
	}

	const deleteCategory = async (record) => {
		await afterSalesApi.categoryDelete({ id: record.id })
		await loadData()
		emit('changed')
	}

	defineExpose({ onOpen })
</script>

<style scoped>
	.category-toolbar {
		display: flex;
		justify-content: flex-end;
		margin-bottom: 12px;
	}
</style>
