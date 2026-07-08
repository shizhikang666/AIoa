<template>
	<xn-form-container
		:class="'printMe'"
		width="800px"
		:bodyStyle="{ paddingTop: 0 }"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<template #title>
			{{ userProcess.title }}
			<a-space>
				<a-tag :color="$TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_state_color', userProcess.status)">
					{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_state', userProcess.status) }}
				</a-tag>
			</a-space>
		</template>
		<br />
		<a-skeleton v-if="!error" active :loading="loading">
			<project-init-info
				v-if="userProcess.category === 'Process_sale_project_init'"
				:id="instanceId"
			></project-init-info>
			<project-payment-info
				v-else-if="userProcess.category === 'Process_sale_project_play'"
				:id="instanceId"
			></project-payment-info>
			<project-delivery-info
				v-else-if="userProcess.category === 'Process_sale_project_delivery'"
				:id="instanceId"
			></project-delivery-info>
			<procure-info v-else-if="userProcess.category === 'Process_procure'" :id="instanceId" />
			<reimbursement-info
				v-else-if="userProcess.category === 'Process_reimbursement'"
				:id="instanceId"
			></reimbursement-info>
			<project-reissue-info
				v-else-if="userProcess.category === 'Process_project_reissue_product'"
				:id="instanceId"
			></project-reissue-info>
			<payment-info :id="instanceId" v-else-if="userProcess.category === 'Process_payment'"></payment-info>
			<procure-warehouse-info
				:id="instanceId"
				v-else-if="userProcess.category === 'Process_procure_in_warehouse'"
			></procure-warehouse-info>
			<makePaymentInfo :id="instanceId" v-else-if="userProcess.category === 'Process_make_payment'"></makePaymentInfo>
			<askForLeaveInfo :id="instanceId" v-else-if="userProcess.category === 'Process_ask_leave'"></askForLeaveInfo>
			<project-return-info
				:id="instanceId"
				v-else-if="userProcess.category === 'Process_sale_project_product_return'"
			></project-return-info>
			<br />
			<a-typography-title v-if="fileList.length" :level="5">附件信息</a-typography-title>
			<br />

			<a-image-preview-group>
				<a-image
					style="border: 1px solid rgba(67, 67, 67, 0.45)"
					v-for="item in imgList"
					:width="100"
					:src="item.downloadPath"
				/>
			</a-image-preview-group>
			<br /><br />
			<a-list size="small" v-if="otherFileList.length" bordered :data-source="otherFileList">
				<template #renderItem="{ item }">
					<a-list-item
						>{{ item.name }}
						<a-space>
							<a-typography-link :href="item.downloadPath">下载</a-typography-link>
							<a-typography-link @click="openFilePreview(item)">预览</a-typography-link>
						</a-space>
					</a-list-item>
				</template>
			</a-list>

			<br />
			<br />

			<a-typography-title :level="5">发起人</a-typography-title>
			<a-list class="demo-loadmore-list" item-layout="horizontal">
				<a-list-item>
					<a-list-item-meta>
						<template #title>
							<a>{{ startUser.name }}</a>
						</template>
						<template #description>
							<div class="hidden_print">
								{{ startOrgTree && startOrgTree.length ? startOrgTree[0].label : '' }}
							</div>
						</template>
						<template #avatar>
							<a-avatar :src="startUser.avatar" />
						</template>
					</a-list-item-meta>
					<div>{{ userProcess.createTime }}</div>
				</a-list-item>
			</a-list>
			<br />
			<template v-for="(task, i) in userActivityList" :key="task.category">
				<a-typography-title v-if="task.taskDetailList.length" :level="5">{{ task.name }}</a-typography-title>
				<a-list
					v-if="task.taskDetailList.length"
					class="demo-loadmore-list"
					item-layout="horizontal"
					:data-source="task.taskDetailList"
				>
					<template #renderItem="{ item }">
						<a-list-item>
							<template #actions>
								<CheckCircleFilled v-if="item.endTime === null || item.form.state == null" />
								<CloseCircleFilled v-else-if="item.form.state === 'REJECT'" :style="{ color: token.colorError }" />
								<CheckCircleFilled v-else-if="item.form.state === 'AGREE'" :style="{ color: token.colorSuccess }" />
							</template>
							<a-list-item-meta>
								<template #title>
									<a>{{ item.bizUser.name }}</a>
								</template>
								<template #description>
									<div class="hidden_print">
										{{ item.form.comment ? item.form.comment : '未填写' }}
									</div>
								</template>
								<template #avatar>
									<a-avatar :src="item.bizUser.avatar" />
								</template>
							</a-list-item-meta>
							<div class="hidden_print">{{ item.endTime === null ? '审核中' : item.endTime }}</div>
						</a-list-item>
					</template>
				</a-list>
				<br />

				<!--
					暂时保留磊哥之前的修改
				<div :class="task.name === '财务支出确认' ? 'hidden_print' : ''">
					<a-typography-title v-if="task.taskDetailList.length" :level="5">{{ task.name }}</a-typography-title>
					<a-list
						v-if="task.taskDetailList.length"
						class="demo-loadmore-list"
						item-layout="horizontal"
						:data-source="task.taskDetailList"
					>
						<template #renderItem="{ item }">
							<a-list-item>
								<template #actions>
									<CheckCircleFilled v-if="item.endTime === null || item.form.state == null" />
									<CloseCircleFilled v-else-if="item.form.state === 'REJECT'" :style="{ color: token.colorError }" />
									<CheckCircleFilled v-else-if="item.form.state === 'AGREE'" :style="{ color: token.colorSuccess }" />
								</template>
								<a-list-item-meta>
									<template #title>
										<a>{{ item.bizUser.name }}</a>
									</template>
									<template #description>
										<div class="hidden_print">
											{{ item.form.comment ? item.form.comment : '未填写' }}
										</div>
									</template>
									<template #avatar>
										<a-avatar :src="item.bizUser.avatar" />
									</template>
								</a-list-item-meta>
								<div class="hidden_print">{{ item.endTime === null ? '审核中' : item.endTime }}</div>
							</a-list-item>
						</template>
					</a-list>
				</div>
				-->
			</template>
			<br />
			<a-typography-title v-if="ccUser.length" :level="5">抄送用户</a-typography-title>
			<a-list v-if="ccUser.length" :data-source="ccUser" item-layout="horizontal">
				<template #renderItem="{ item }">
					<a-list-item>
						<a-avatar-group>
							<a-tooltip :title="item.name" placement="top">
								<a-avatar :src="item.avatar"></a-avatar>
							</a-tooltip>
						</a-avatar-group>
					</a-list-item>
				</template>
			</a-list>
			<br />
			<a-row v-if="showTaskForm && loadingTaskInfo" justify="center">
				<a-spin />
			</a-row>
			<div class="hidden_print" v-else-if="showTaskForm">
				<!--审批表单-->
				<approval-task-form
					:instanceId="instanceId"
					@successful="
						() => {
							onClose()
							$emit('successful')
						}
					"
					:taskDetail="taskDetail"
					v-if="isActivityApproval"
				>
				</approval-task-form>
				<!--财务收款表单-->
				<payment-approval-task-form
					:instanceId="instanceId"
					@successful="
						() => {
							onClose()
							$emit('successful')
						}
					"
					:taskDetail="taskDetail"
					v-if="taskDetail.category === 'Activity_payment_approval'"
				>
					task-detail="">
				</payment-approval-task-form>
				<pay-approval-task-form
					:instanceId="instanceId"
					@successful="
						() => {
							onClose()
							$emit('successful')
						}
					"
					:taskDetail="taskDetail"
					v-if="taskDetail.category === 'Activity_pay_approval'"
				>
				</pay-approval-task-form>

				<process-task-form
					@successful="
						() => {
							onClose()
							$emit('successful')
						}
					"
					:taskDetail="taskDetail"
					v-if="taskDetail.category === 'Activity_procure_approval'"
				></process-task-form>
			</div>
		</a-skeleton>
		<div v-else-if="error">
			<a-space style="width: 100%" direction="vertical" align="center">
				<a-result status="500" title="500" sub-title="服务器错误">
					<template #extra>
						<a-button type="primary" @click="onClose">关闭</a-button>
					</template>
				</a-result>
			</a-space>
		</div>
		<template #footer>
			<a-row justify="end" class="hidden_print">
				<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
				<a-button style="margin-right: 8px" @click="handlePrint">打印</a-button>
			</a-row>
		</template>
	</xn-form-container>
</template>

<script setup name="ProcessDetails">
	import { cloneDeep, debounce } from 'lodash-es'
	import { theme } from 'ant-design-vue'
	import { vPrint } from 'vue-print-next'

	import { VuePrintNext } from 'vue-print-next'

	const { useToken } = theme
	const { token } = useToken()
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import bizTaskApi from '@/api/biz/bizTaskApi'

	import tool from '@/utils/tool'
	import ApprovalTaskForm from '@/views/biz/biztask/taskForm/approvalTaskForm.vue'
	import PaymentApprovalTaskForm from '@/views/biz/biztask/taskForm/paymentApprovalTaskForm.vue'
	import ProjectInitInfo from './infoForm/project/projectInitInfo.vue'
	import ProjectPaymentInfo from './infoForm/project/projectPaymentInfo.vue'
	import ProjectDeliveryInfo from './infoForm/project/projectDeliveryInfo.vue'
	import ProcureInfo from './infoForm/procure/procureInfo.vue'
	import ReimbursementInfo from './infoForm/payment/reimbursementInfo.vue'
	import PayApprovalTaskForm from '@/views/biz/biztask/taskForm/payApprovalTaskForm.vue'
	import { openFilePreview } from '@/utils/filePreview'
	import ProjectReissueInfo from './infoForm/project/projectReissueInfo.vue'
	import PaymentInfo from './infoForm/payment/paymentInfo.vue'
	import ProcessTaskForm from '@/views/biz/biztask/taskForm/processTaskForm.vue'
	import ProcureWarehouseInfo from './infoForm/procure/procureWarehouseInfo.vue'
	import makePaymentInfo from './infoForm/payment/makePaymentInfo.vue'
	import askForLeaveInfo from './infoForm/personnel/askForLeaveInfo.vue'
	import ProjectReturnInfo from '@/views/biz/bizprocess/processDetails/infoForm/project/projectReturnInfo.vue'

	const open = ref(false)
	// 表单数据
	const loading = ref(false)
	const error = ref(false)
	const userProcess = ref({})
	const startUser = ref({})
	const startOrgTree = ref([])
	const userActivityList = ref([])
	const ccUser = ref([])
	const emit = defineEmits({ successful: null })
	const loadingTaskInfo = ref(false)
	const showTaskForm = ref(false)
	const taskDetail = ref({})
	function handlePrint() {
		const originalTitle = document.title
		new VuePrintNext({
			el: '.printMe',
			orientation: 'portrait',
			noPrintSelector: ['.ant-drawer-close', '.ant-tag', '.hidden_print'],
			openCallback() {
				// 保存原始标题
				// 修改标题
				document.title = userProcess.value.title || '审批详情'
			},
			closeCallback() {
				// 恢复原始标题
				document.title = originalTitle
			}
		})
	}
	const loadTaskForm = async (id) => {
		showTaskForm.value = true
		loadingTaskInfo.value = true
		try {
			const res = await bizTaskApi.runtimeActivityDetail({ id })
			taskDetail.value = res
		} catch (e) {
			showTaskForm.value = false
		} finally {
			loadingTaskInfo.value = false
		}
	}
	const instanceId = ref('')
	const fileList = ref([])
	const imgList = ref([])
	const otherFileList = ref([])

	const isActivityApproval = computed(() => {
		return taskDetail.value?.category?.startsWith('Activity_approval')
	})

	// 打开抽屉
	const onOpen = async (record, taskId) => {
		open.value = true
		try {
			loading.value = true
			instanceId.value = record.instanceId
			const result = await bizProcessApi.bizProcessDetail({
				id: record.instanceId
			})
			userProcess.value = result.userProcess
			startUser.value = result.startUser
			startOrgTree.value = result.startOrgTree
			userActivityList.value = result.userActivityList
			ccUser.value = result.ccUser

			fileList.value = await bizProcessApi.bizFileList({
				id: record.instanceId
			})
			const imgSuffix = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'webp']
			imgList.value = fileList.value.filter((v) => {
				return imgSuffix.includes(v.suffix)
			})

			otherFileList.value = fileList.value.filter((v) => {
				return !imgSuffix.includes(v.suffix)
			})

			const currentTaskId = taskId || result.currentTask?.taskId || result.currentTaskId
			if (currentTaskId) {
				await loadTaskForm(currentTaskId).then()
			} else {
				showTaskForm.value = false
				taskDetail.value = {}
			}
		} catch (e) {
			console.error(e)
			error.value = true
		} finally {
			loading.value = false
		}
	}
	// 关闭抽屉
	const onClose = () => {
		open.value = false
		loading.value = false
		error.value = false
	}

	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
