<template>
	<xn-form-container
		:title="formData.id ? '编辑结算账户表' : '增加结算账户表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="horizontal">
			<a-form-item label="账户名称：" name="accountName">
				<a-input v-model:value="formData.accountName" placeholder="请输入账户名称" allow-clear />
			</a-form-item>
			<a-form-item v-if="!formData.id" label="账户编号：" name="accountNumber">
				<a-input v-model:value="formData.accountNumber" placeholder="请输入账户编号" allow-clear />
			</a-form-item>
			<a-form-item v-if="!formData.id" label="初始资金：" name="initialAmount">
				<a-input-number placeholder="请输入初始资金" allow-clear v-model:value="formData.initialAmount">
					<template #addonAfter> ¥</template>
				</a-input-number>
			</a-form-item>

			<a-form-item label="是否启用：" name="accountStatus">
				<a-select v-model:value="formData.accountStatus" placeholder="请选择是否启用" :options="accountStatusOptions" />
			</a-form-item>
			<a-form-item v-if="formData.id" label="所属机构组织：" name="org">
				<a-tree-select
					v-model:value="formData.org"
					class="xn-wd"
					:dropdown-style="{ maxHeight: '400px', overflow: 'auto' }"
					placeholder="请选择组织"
					allow-clear
					tree-default-expand-all
					:tree-data="treeData"
					:tree-default-expanded-keys="treeDefaultExpandedKeys"
					:field-names="{
						children: 'children',
						label: 'name',
						value: 'id'
					}"
				></a-tree-select>
			</a-form-item>

			<a-form-item label="排序：" name="sortCode">
				<a-input-number placeholder="排序" allow-clear min="0" max="99" v-model:value="formData.sortCode">
				</a-input-number>
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="settlementAccountForm">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import settlementAccountApi from '@/api/biz/settlementAccountApi'
	import { useOrg } from '@/composables/useOrg'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const accountStatusOptions = ref([])

	const { treeData, treeDefaultExpandedKeys, loadingTreeData } = useOrg()

	// 打开抽屉
	const onOpen = (record) => {
		open.value = true

		formData.value = {}
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}

		accountStatusOptions.value = tool.dictList('COMMON_STATUS')
		if (!formData.value.accountStatus) {
			formData.value.accountStatus = accountStatusOptions.value[0].value
		}
		loadingTreeData()
	}
	// 关闭抽屉
	const onClose = () => {
		open.value = false
	}
	// 默认要校验的
	const formRules = {
		accountName: [required('请输入账户名称')],
		accountNumber: [required('请输入账户编号')]
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				settlementAccountApi
					.settlementAccountSubmitForm(formDataParam, formDataParam.id)
					.then(() => {
						emit('successful')
						onClose()
					})
					.finally(() => {
						submitLoading.value = false
					})
			})
			.catch(() => {})
	}
	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
