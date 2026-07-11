<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="16">
				<a-col :span="6">
					<a-form-item label="关键词" name="searchKey">
						<a-input v-model:value="searchFormState.searchKey" allow-clear placeholder="标题、内容、项目或分类" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="售后分类" name="categoryId">
						<a-select
							v-model:value="searchFormState.categoryId"
							allow-clear
							:options="categoryOptions"
							placeholder="全部分类"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="处理时间" name="handleTime">
						<a-range-picker
							v-model:value="searchFormState.handleTime"
							value-format="YYYY-MM-DD HH:mm:ss"
							show-time
							style="width: 100%"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh(true)">查询</a-button>
					<a-button class="xn-ml8" @click="reset">重置</a-button>
					<a @click="advanced = !advanced" style="margin-left: 12px">
						{{ advanced ? '收起' : '展开' }}
						<component :is="advanced ? 'up-outlined' : 'down-outlined'" />
					</a>
				</a-col>
				<a-col v-show="advanced" :span="6">
					<a-form-item label="关联项目" name="projectName">
						<a-input v-model:value="searchFormState.projectName" allow-clear placeholder="项目名称" />
					</a-form-item>
				</a-col>
				<a-col v-show="advanced" :span="6">
					<a-form-item label="创建人" name="createUserName">
						<a-input v-model:value="searchFormState.createUserName" allow-clear placeholder="创建人姓名" />
					</a-form-item>
				</a-col>
			</a-row>
		</a-form>

		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #operator>
				<a-space>
					<a-button type="primary" @click="formRef.onOpen()">
						<template #icon><plus-outlined /></template>
						新增售后记录
					</a-button>
					<a-button @click="categoryRef.onOpen()">
						<template #icon><tags-outlined /></template>
						分类管理
					</a-button>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'title'">
					<a @click="detailRef.onOpen(record)">{{ record.title }}</a>
				</template>
				<template v-if="column.dataIndex === 'categoryName'">
					<a-tag color="blue">{{ record.categoryName }}</a-tag>
				</template>
				<template v-if="column.dataIndex === 'projectName'">
					{{ record.projectName || '--' }}
				</template>
				<template v-if="column.dataIndex === 'attachmentCount'">
					<a-badge :count="record.attachmentCount" :show-zero="true" />
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="detailRef.onOpen(record)">查看</a>
						<a v-if="record.canEdit" @click="formRef.onOpen(record)">编辑</a>
						<a-popconfirm v-if="record.canEdit" title="确定删除这条售后记录？" @confirm="deleteRecord(record)">
							<a-typography-link type="danger">删除</a-typography-link>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>

	<Form ref="formRef" @successful="tableRef.refresh(true)" />
	<Detail ref="detailRef" />
	<CategoryManager ref="categoryRef" @changed="categoryChanged" />
</template>

<script setup name="afterSalesIndex">
	import { cloneDeep } from 'lodash-es'
	import afterSalesApi from '@/api/biz/afterSalesApi'
	import Form from './form.vue'
	import Detail from './detail.vue'
	import CategoryManager from './categoryManager.vue'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const detailRef = ref()
	const categoryRef = ref()
	const advanced = ref(false)
	const categoryOptions = ref([])
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const columns = [
		{ title: '标题', dataIndex: 'title', width: 220, ellipsis: true },
		{ title: '分类', dataIndex: 'categoryName', width: 120 },
		{ title: '关联项目', dataIndex: 'projectName', width: 180, ellipsis: true },
		{ title: '处理内容', dataIndex: 'contentSummary', ellipsis: true },
		{ title: '附件', dataIndex: 'attachmentCount', width: 70, align: 'center' },
		{ title: '创建人', dataIndex: 'createUserName', width: 110 },
		{ title: '所属组织', dataIndex: 'createUserOrgName', width: 130, ellipsis: true },
		{ title: '处理时间', dataIndex: 'handleTime', width: 165 },
		{ title: '操作', dataIndex: 'action', fixed: 'right', width: 150 }
	]

	const loadCategories = async () => {
		const data = await afterSalesApi.categoryList()
		categoryOptions.value = data.map((item) => ({ label: item.name, value: item.id }))
	}

	const loadData = (parameter) => {
		const params = cloneDeep(searchFormState.value)
		if (params.handleTime) {
			params.startHandleTime = params.handleTime[0]
			params.endHandleTime = params.handleTime[1]
			delete params.handleTime
		}

		return afterSalesApi.afterSalesPage(Object.assign(parameter, params))
	}

	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}

	const deleteRecord = async (record) => {
		await afterSalesApi.afterSalesDelete({ id: record.id })
		tableRef.value.refresh(true)
	}

	const categoryChanged = async () => {
		await loadCategories()
		tableRef.value.refresh(true)
	}

	onMounted(loadCategories)
</script>
