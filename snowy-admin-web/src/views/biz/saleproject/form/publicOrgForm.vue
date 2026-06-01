<template>
	<xn-form-container title="录入公共订单" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="客户名称：" name="customerName">
				<a-input v-model:value="formData.customerName" />
			</a-form-item>
			<a-form-item label="项目名称：" name="projectName">
				<a-input v-model:value="formData.projectName" />
			</a-form-item>
			<a-form-item label="合同金额" name="initPrice">
				<XnCurrencyInput v-model:value="formData.initPrice"></XnCurrencyInput>
			</a-form-item>
			<a-form-item label="已收款金额" name="historyAmount">
				<XnCurrencyInput v-model:value="formData.historyAmount"></XnCurrencyInput>
			</a-form-item>
			<a-form-item label="项目成交日期：" name="completionDate">
				<a-date-picker v-model:value="formData.completionDate" value-format="YYYY-MM-DD HH:mm:ss"></a-date-picker>
			</a-form-item>
			<!--			<a-form-item label="负责人：" name="user">-->
			<!--				<xn-user-selector-->
			<!--					:dataIsConverterFlw="false"-->
			<!--					:radioModel="true"-->
			<!--					:org-tree-api="selectorApiFunction.orgTreeApi"-->
			<!--					:user-page-api="selectorApiFunction.userPageApi"-->
			<!--					:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"-->
			<!--					data-type="object"-->
			<!--					v-model:value="formData.user"-->
			<!--				/>-->
			<!--			</a-form-item>-->
			<a-form-item label="所属组织：" name="orgId">
				<a-tree-select
					v-model:value="formData.orgId"
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

			<a-form-item label="备注" name="remark">
				<a-textarea v-model:value="formData.remark" :rows="4" />
			</a-form-item>
		</a-form>

		<template #footer>
			<a-row justify="end">
				<a-col>
					<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
					<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
				</a-col>
			</a-row>
		</template>
	</xn-form-container>
</template>
<script setup lang="js">
	import { ref, useTemplateRef } from 'vue'
	import tool from '@/utils/tool'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { required } from '@/utils/formRules'
	import BizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import { cloneDeep } from 'lodash-es'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const formRef = useTemplateRef('formRef')
	const formData = ref({})
	const open = ref(false)
	const emit = defineEmits({ successful: null })

	const pacOptions = ref([])
	const projectCategoryOptions = ref([])
	const freightCategoryOptions = ref(tool.dictList('FREIGHT_CATEGORY'))
	const selectorApiFunction = useUserSelector()
	projectCategoryOptions.value = tool.dictListByPath('SALE_PROJECT', 'PROJECT_CATEGORY')
	pacOptions.value = tool.pcaDataAll()
	const submitLoading = ref(false)
	const formRules = {
		orgId: [required('请选择所属组织')],
		customerName: [required('请输入客户名称')],
		projectName: [required('请输入项目名称')],
		initPrice: [required('请输入合同金额')],
		historyAmount: [required('请输入已收款金额')],
		completionDate: [required('请输入成交日期')]
	}

	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		formRef.value.resetFields()

		open.value = false
	}
	const onOpen = () => {
		open.value = true
	}

	const onSubmit = async () => {
		await formRef.value.validate()
		submitLoading.value = true
		const formDataParam = cloneDeep(formData.value)
		try {
			await BizSaleProjectApi.bizSpecialProjectAdd(formDataParam)
			emit('successful')
			onClose()
		} finally {
			submitLoading.value = false
		}
	}

	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
