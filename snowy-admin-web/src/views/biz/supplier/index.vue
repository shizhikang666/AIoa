<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="供应商名称" name="name">
						<a-input v-model:value="searchFormState.name" placeholder="请输入供应商名称" />
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
					<a-form-item label="开户行" name="bankName">
						<a-input v-model:value="searchFormState.bankName" placeholder="请输入开户行" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="银行账户" name="bankAccount">
						<a-input v-model:value="searchFormState.bankAccount" placeholder="请输入银行账户" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="供应商状态" name="status">
						<a-select v-model:value="searchFormState.status" placeholder="请选择供应商状态"
								  :options="statusOptions" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="企业性质" name="enterpriseNature">
						<a-input v-model:value="searchFormState.enterpriseNature" placeholder="请输入企业性质" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="税务登记号" name="taxRegistrationNumber">
						<a-input v-model:value="searchFormState.taxRegistrationNumber" placeholder="请输入税务登记号" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="结算方式" name="paymentMethod">
						<a-input v-model:value="searchFormState.paymentMethod" placeholder="请输入结算方式" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					<a @click="toggleAdvanced" style="margin-left: 8px">
						{{ advanced ? "收起" : "展开" }}
						<component :is="advanced ? 'up-outlined' : 'down-outlined'" />
					</a>
				</a-col>
			</a-row>
		</a-form>
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			:alert="options.alert.show"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
			:row-selection="options.rowSelection"
			:scroll="{
				x: 1300
			}"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('supplierAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>
					<xn-batch-delete
						v-if="hasPerm('supplierBatchDelete')"
						:selectedRowKeys="selectedRowKeys"
						@batchDelete="deleteBatchSupplier"
					/>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'name'">
					<a-typography-link @click="supplierDetailRef.onOpen(record.id)">{{ record.name }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'status'">
					{{ $TOOL.dictTypeData("COMMON_STATUS", record.status) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('supplierEdit')">编辑</a>
						<a-divider type="vertical" v-if="hasPerm(['supplierEdit', 'supplierDelete'], 'and')" />
						<a-popconfirm title="确定要删除吗？" @confirm="deleteSupplier(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('supplierDelete')">删除</a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />

	<Detail ref="supplierDetailRef"></Detail>
</template>

<script setup name="supplier">
import tool from "@/utils/tool";
import { cloneDeep } from "lodash-es";
import Form from "./form.vue";
import supplierApi from "@/api/biz/supplierApi";
import Detail from "@/views/biz/supplier/detail/index.vue";
import { useTemplateRef } from "vue";

const supplierDetailRef = useTemplateRef("supplierDetailRef");

const searchFormState = ref({});
const searchFormRef = ref();
const tableRef = ref();
const formRef = ref();
const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false };
// 查询区域显示更多控制
const advanced = ref(false);
const toggleAdvanced = () => {
	advanced.value = !advanced.value;
};
const columns = [
	{
		title: "供应商名称",
		dataIndex: "name",
		width: 300
	},
	{
		title: "别名",
		dataIndex: "aliasName"
	},
	{
		title: "联系人",
		dataIndex: "contacts"
	},
	{
		title: "联系电话",
		dataIndex: "phone"
	},
	{
		title: "开户行",
		dataIndex: "bankName",
		ellipsis: true
	},
	{
		title: "银行账户",
		dataIndex: "bankAccount"
	},
	{
		title: "供应商状态",
		dataIndex: "status"
	},
	{
		title: "企业性质",
		dataIndex: "enterpriseNature"
	},
	{
		title: "税务登记号",
		dataIndex: "taxRegistrationNumber"
	},
	{
		title: "结算方式",
		dataIndex: "paymentMethod"
	}

	// {
	// 	title: '排序编码',
	// 	dataIndex: 'sortCode'
	// }
];
// 操作栏通过权限判断是否显示
if (hasPerm(["supplierEdit", "supplierDelete"])) {
	columns.push({
		title: "操作",
		dataIndex: "action",
		align: "center",
		width: 150
	});
}
const selectedRowKeys = ref([]);
// 列表选择配置
const options = {
	// columns数字类型字段加入 needTotal: true 可以勾选自动算账
	alert: {
		show: true,
		clear: () => {
			selectedRowKeys.value = ref([]);
		}
	},
	rowSelection: {
		onChange: (selectedRowKey, selectedRows) => {
			selectedRowKeys.value = selectedRowKey;
		}
	}
};
const loadData = (parameter) => {
	const searchFormParam = cloneDeep(searchFormState.value);
	return supplierApi.supplierPage(Object.assign(parameter, searchFormParam)).then((data) => {
		return data;
	});
};
// 重置
const reset = () => {
	searchFormRef.value.resetFields();
	tableRef.value.refresh(true);
};
// 删除
const deleteSupplier = (record) => {
	let params = [
		{
			id: record.id
		}
	];
	supplierApi.supplierDelete(params).then(() => {
		tableRef.value.refresh(true);
	});
};
// 批量删除
const deleteBatchSupplier = (params) => {
	supplierApi.supplierDelete(params).then(() => {
		tableRef.value.clearRefreshSelected();
	});
};
const statusOptions = tool.dictList("COMMON_STATUS");
</script>
