<template>
	<a-form
		ref="formRef"
		layout="vertical"
		:label-col="{ ...layout.labelCol, offset: 0 }"
		:wrapper-col="{ ...layout.wrapperCol, offset: 0 }"
	>
		<a-form-item v-if="showOpen" label="是否开启审批流程：" name="open">
			<a-space direction="vertical">
				<a-radio-group v-model:value="open">
					<a-radio :value="true">开启</a-radio>
					<a-radio :value="false">关闭</a-radio>
				</a-radio-group>
			</a-space>
		</a-form-item>

		<a-form-item v-if="showTreasurer" label="财务：" name="treasurer">
			<xn-user-selector
				:disabled="!open"
				:dataIsConverterFlw="false"
				:radioModel="true"
				:org-tree-api="selectorApiFunction.orgTreeApi"
				:user-page-api="selectorApiFunction.userPageApi"
				:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
				v-model:value="treasurer"
			/>
		</a-form-item>
		<a-form-item v-if="showProcure" label="采购：" name="treasurer">
			<xn-user-selector
				:disabled="!open"
				:dataIsConverterFlw="false"
				:radioModel="true"
				:org-tree-api="selectorApiFunction.orgTreeApi"
				:user-page-api="selectorApiFunction.userPageApi"
				:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
				v-model:value="procure"
			/>
		</a-form-item>

		<a-form-item label="审批人：" name="approveUserIdList">
			<xn-user-selector
				:disabled="!open"
				:org-tree-api="selectorApiFunction.orgTreeApi"
				:user-page-api="selectorApiFunction.userPageApi"
				:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
				data-type="object"
				v-model:value="approveUserIdList"
			/>
		</a-form-item>
		<a-form-item label="抄送人：" name="copyUserIdList">
			<xn-user-selector
				:disabled="!open"
				:org-tree-api="selectorApiFunction.orgTreeApi"
				:user-page-api="selectorApiFunction.userPageApi"
				:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
				data-type="object"
				v-model:value="copyUserIdList"
			/>
		</a-form-item>
	</a-form>
</template>
<script setup name="theProcessForm">
	import { useUserSelector } from '@/composables/useUserSelector'
	import userCenterApi from '@/api/sys/userCenterApi'

	const selectorApiFunction = useUserSelector()
	const formRef = ref()
	const copyUserIdList = defineModel('copyUserIdList')
	const approveUserIdList = defineModel('approveUserIdList')
	const treasurer = defineModel('treasurer')
	const open = defineModel('open')
	const procure = defineModel('procure')

	const { processKey, showTreasurer, showOpen } = defineProps({
		processKey: {
			type: String,
			required: true
		},
		showTreasurer: {
			type: Boolean,
			default: false
		},
		showOpen: {
			type: Boolean,
			default: false
		},
		showProcure: {
			type: Boolean,
			default: false
		}
	})

	watch(
		() => open.value,
		() => {
			if (!open.value) {
				copyUserIdList.value = []
				approveUserIdList.value = []
				treasurer.value = ''
				procure.value = ''
			}
		}
	)

	const layout = {
		labelCol: {
			span: 4
		},
		wrapperCol: {
			span: 12
		}
	}
</script>
<style scoped></style>
