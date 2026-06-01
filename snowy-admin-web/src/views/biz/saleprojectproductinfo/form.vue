<template>
	<xn-form-container
		:title="formData.id ? '编辑软件打包表' : '增加软件打包表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">

			<a-form-item label="软件通用名称：" name="alias">
				<a-input v-model:value="formData.alias" placeholder="请输入内容" allow-clear />
			</a-form-item>

			<a-form-item label="版本类型：" name="versionType">
				<a-input v-model:value="formData.versionType" placeholder="请输入内容" allow-clear />
			</a-form-item>

			<a-form-item label="配置硬件：" name="hardware">
				<a-input v-model:value="formData.hardware" placeholder="请输入内容" allow-clear />
			</a-form-item>

			<a-form-item label="软件简称：" name="abbreviation">
				<a-input v-model:value="formData.abbreviation" placeholder="请输入内容" allow-clear />
			</a-form-item>


			<a-form-item label="备注：" name="remark">
				<a-input v-model:value="formData.remark" placeholder="请输入内容" allow-clear />
			</a-form-item>

			<a-form-item label="版本备注：" name="versionRemark">

				<a-select
					v-model:value="formData.versionRemark"
					show-search
					placeholder="input search text"
					:default-active-first-option="false"
					:show-arrow="false"
					:filter-option="false"
					:not-found-content="null"
					:options="options"
					@blur="onBlur"
					@search="handleChange"
				></a-select>

				<!-- <a-input v-model:value="formData.versionRemark" placeholder="智心|创煜|普德|国盛|中性|客户简称" allow-clear />
	 -->
			</a-form-item>

			<a-form-item label="原序列码：" name="oldCode">
				<a-input v-model:value="formData.oldCode" placeholder="请输入内容" allow-clear />
			</a-form-item>

			<a-form-item label="加密狗：" name="contentText">
				<a-input v-model:value="formData.contentText" placeholder="请输入内容" allow-clear />
			</a-form-item>

		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizSaleProjectProductInfoForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizSaleProjectProductInfoApi from '@/api/biz/bizSaleProjectProductInfoApi'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)

	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record);

			// 设置默认值
			if(!recordData.versionType){
				recordData.versionType = 'v1'
			}

			if(!recordData.remark){
				recordData.remark = '/'
			}

			if(!recordData.oldCode){
				recordData.oldCode = '/'
			}

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
				bizSaleProjectProductInfoApi
					.bizSaleProjectProductInfoSubmitForm(formDataParam, formDataParam.id)
					.then(() => {
						onClose()
						emit('successful', formDataParam.targetId)
					})
					.finally(() => {
						submitLoading.value = false
					})
			})
			.catch(() => {})
	}
	//select选项

	const options= ref([
		{
				label:'',
				value:''
			},
			{
				label:'智心',
				value:'智心'
			},
			{
				label:'普德',
				value:'普德'
			},
			{
				label:'创煜',
				value:'创煜'
			},
			{
				label:'国盛',
				value:'国盛'
			},
			{
				label:'中性',
				value:'中性'
			}


	])

	 const handleChange = (e)=>{
		options.value[0] = {
			label:e,
			value:e
		}
		formData.value.versionRemark = e;
	}
	const onBlur = ()=>{

		if(!formData.value.versionRemark ){
			formData.value.versionRemark = options.value[0].value;
		}

	}


	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
