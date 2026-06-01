<template>
	<a-card :bordered="false">
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			:alert="options.alert.show"
			bordered
			:row-key="(record) => record.tenantId"
			:tool-config="toolConfig"
			:row-selection="options.rowSelection"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('tenantsAdd')">
						<template #icon><plus-outlined /></template>
						新增
					</a-button>
					<xn-batch-delete
						v-if="hasPerm('tenantsBatchDelete')"
						:selectedRowKeys="selectedRowKeys"
						@batchDelete="deleteBatchTenants"
					/>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="copyLink(record)">复制登录连接</a>
						<a-divider type="vertical" v-if="hasPerm(['tenantsEdit', 'tenantsDelete'], 'and')" />
						<a @click="formRef.onOpen(record)" v-if="hasPerm('tenantsEdit')">编辑</a>
						<a-divider type="vertical" v-if="hasPerm(['tenantsEdit', 'tenantsDelete'], 'and')" />
						<a-popconfirm title="确定要删除吗？" @confirm="deleteTenants(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('tenantsDelete')">删除</a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
</template>

<script setup name="tenant">
import { App } from 'ant-design-vue';

const { message} = App.useApp();
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import tenantsApi from '@/api/tenant/tenantsApi'
	import { useClipboard } from '@vueuse/core'
	const {  copy } = useClipboard({  })
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const columns = [
		{
			width:100,
			title: '租户编码',
			dataIndex: 'tenantId'
		},
		{
			title: '租户名称',
			dataIndex: 'tenantName'
		},
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['tenantsEdit', 'tenantsDelete'])) {
		columns.push({
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 300
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
	const loadData = (parameter) => {
		return tenantsApi.tenantsPage(parameter).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteTenants = (record) => {
		let params = [
			{
				id: record.tenantId
			}
		]
		tenantsApi.tenantsDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchTenants = (params) => {
		tenantsApi.tenantsDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}

	const  copyLink = (record)=>{

		 const baseUrl = `${window.location.protocol}//${window.location.hostname}:${window.location.port}/login?tenantId=${record.tenantId}&name=${record.tenantName}`;

		copy(baseUrl);
		message.success("复制成功")
	}

</script>
