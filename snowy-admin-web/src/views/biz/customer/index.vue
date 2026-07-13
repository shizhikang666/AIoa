<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="名称" name="name">
						<a-input v-model:value="searchFormState.name" placeholder="请输入名称" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="联系人" name="contacts">
						<a-input v-model:value="searchFormState.contacts" placeholder="请输入联系人" />
					</a-form-item>
				</a-col>

				<a-col :span="6">
					<a-form-item label="联系电话" name="phone">
						<a-input v-model:value="searchFormState.phone" placeholder="请输入联系电话" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="详细地址" name="detailsAddress">
						<a-input v-model:value="searchFormState.detailsAddress" placeholder="请输入详细地址" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="客户地区" name="address">
						<a-input v-model:value="searchFormState.address" placeholder="请输入客户地区" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="状态" name="status">
						<a-select v-model:value="searchFormState.status" placeholder="请选择状态" :options="statusOptions" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="负责人" name="headName">
						<a-input v-model:value="searchFormState.headName" placeholder="请输入负责人名称" />
					</a-form-item>
				</a-col>

				<a-col :span="7" v-show="advanced">
					<a-form-item label="创建人" name="createUserName">
						<a-input v-model:value="searchFormState.createUserName" placeholder="请输入创建人名称" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="所属组织：" name="orgId">
						<a-tree-select
							v-model:value="searchFormState.orgId"
							class="xn-wd"
							:dropdown-style="{ maxHeight: '400px', overflow: 'auto' }"
							placeholder="请选择组织"
							allow-clear
							:tree-data="treeData"
							:field-names="{
								children: 'children',
								label: 'name',
								value: 'id'
							}"
							selectable="false"
							tree-line
						></a-tree-select>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					<a @click="toggleAdvanced" style="margin-left: 8px">
						{{ advanced ? '收起' : '展开' }}
						<component :is="advanced ? 'up-outlined' : 'down-outlined'" />
					</a>
				</a-col>
			</a-row>
		</a-form>
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			bordered
			:scroll="{ x: 1300 }"
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('customerAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>

					<a-button type="primary" @click="ExportCustomerRef.onOpen()" v-if="hasPerm('customerExport')">
						<template #icon> <DownloadOutlined /> </template> 导出客户
					</a-button>
					<a-checkbox v-model:checked="showRepeat"> 筛选电话重复</a-checkbox>

					<!--					<xn-batch-delete-->
					<!--						v-if="hasPerm('customerBatchDelete')"-->
					<!--						:selectedRowKeys="selectedRowKeys"-->
					<!--						@batchDelete="deleteBatchCustomer"-->
					<!--					/>-->
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'name'">
					<a-typography-link v-if="hasPerm('customerDetails')" @click="detailsPage.onOpen(record)">
						{{ record.name }}
					</a-typography-link>
					<template v-else>{{ record.name }}</template>
				</template>
				<template v-if="column.dataIndex === 'sourceType'">
					{{ $TOOL.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_SOURCE', record.sourceType) }}
				</template>
				<template v-if="column.dataIndex === 'customType'">
					{{ $TOOL.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_TYPE', record.customType) }}
				</template>
				<template v-if="column.dataIndex === 'status'">
					{{ $TOOL.dictTypeData('COMMON_STATUS', record.status) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('customerEdit')">编辑</a>
						<a-divider type="vertical" v-if="hasPerm(['customerEdit', 'customerDelete'], 'and')" />
						<a-popconfirm title="确定要删除吗？" @confirm="deleteCustomer(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('customerDelete')">删除</a-button>
						</a-popconfirm>
						<a-divider type="vertical" v-if="hasPerm(['customerDetails'])" />
						<a-dropdown v-if="hasPerm(['customerDetails'])">
							<a class="ant-dropdown-link">
								{{ $t('common.more') }}
								<DownOutlined />
							</a>
							<template #overlay>
								<a-menu>
									<a-menu-item v-if="hasPerm('customerDetails')">
										<a @click="detailsPage.onOpen(record)">{{ $t('common.detailButton') }}</a>
									</a-menu-item>
								</a-menu>
							</template>
						</a-dropdown>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<CustomerDetails ref="detailsPage"></CustomerDetails>
	<ExportCustomer ref="ExportCustomerRef" />
</template>

<script setup name="customer">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import customerApi from '@/api/biz/customerApi'
	import CustomerDetails from './customerDetails/customerDetails.vue'
	import { useOrg } from '@/composables/useOrg'
	import ExportCustomer from './exportCustomer.vue'

	const showRepeat = ref(false)
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const detailsPage = ref()
	const ExportCustomerRef = ref()

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '客户单位名称',
			dataIndex: 'name',
			width: 250
		},
		{
			title: '联系人',
			dataIndex: 'contacts',
			width: 100
		},
		{
			title: '联系电话',
			dataIndex: 'phone',
			ellipsis: true,
			width: 100
		},
		// {
		// 	title: '详细地址',
		// 	dataIndex: 'detailsAddress',
		//
		// },
		{
			title: '客户地区',
			dataIndex: 'address',
			ellipsis: true,
			width: 100
		},
		{
			title: '客户来源',
			dataIndex: 'sourceType',
			ellipsis: true,
			width: 100
		},
		{
			title: '客户类型',
			dataIndex: 'customType',
			width: 100
		},
		{
			title: '所属组织',
			dataIndex: 'orgName',
			ellipsis: true,
			width: 100
		},
		{
			title: '所属用户',
			dataIndex: 'headName',
			width: 100
		},
		{
			title: '创建人',
			dataIndex: 'createUserName',
			width: 100
		},
		// {
		// 	title: '状态',
		// 	dataIndex: 'status'
		// },
		{
			title: '创建时间',
			dataIndex: 'createTime',
			ellipsis: true,
			width: 100
		}
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['customerEdit', 'customerDelete', 'customerDetails'])) {
		columns.push({
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 200
		})
	}
	const selectedRowKeys = ref([])
	// 列表选择配置
	const options = {
		// columns数字类型字段加入 needTotal: true 可以勾选自动算账
		alert: {
			show: true,
			clear: () => {
				selectedRowKeys.value = ref([])
			}
		},
		rowSelection: {
			onChange: (selectedRowKey, selectedRows) => {
				selectedRowKeys.value = selectedRowKey
			}
		}
	}

	watch(showRepeat, (newVal, oldVal) => {
		tableRef.value.refresh()
	})
	const loadData = (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		return customerApi
			.customerPage(Object.assign(parameter, searchFormParam, { showRepeat: showRepeat.value }))
			.then((data) => {
				return data
			})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteCustomer = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		customerApi.customerDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchCustomer = (params) => {
		customerApi.customerDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
	const statusOptions = tool.dictList('COMMON_STATUS')
</script>
