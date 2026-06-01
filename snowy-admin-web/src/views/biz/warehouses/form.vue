<template>
	<xn-form-container
		:title="formData.id ? '编辑系统仓库表' : '增加系统仓库表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="仓库名称：" name="name">
				<a-input v-model:value="formData.name" placeholder="请输入仓库名称" allow-clear />
			</a-form-item>
			<a-form-item label="仓库编码：" name="code">
				<a-input v-model:value="formData.code" placeholder="请输入仓库编码" allow-clear />
			</a-form-item>
			<a-form-item label="仓库地址：" name="address">
				<a-input v-model:value="formData.address" placeholder="请输入仓库地址" allow-clear />
			</a-form-item>
			<a-form-item v-if="formData.id" label="所属组织：" name="org">
				<a-tree-select
					v-model:value="formData.org"
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

			<a-form-item label="排序码：" name="sortCode">
				<a-input v-model:value="formData.sortCode" placeholder="请输入排序码" allow-clear />
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="warehousesForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import warehousesApi from '@/api/biz/warehousesApi'
	import { useOrg } from '@/composables/useOrg'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}
	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = {}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				warehousesApi
					.warehousesSubmitForm(formDataParam, formDataParam.id)
					.then(() => {
						onClose()
						emit('successful')
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
