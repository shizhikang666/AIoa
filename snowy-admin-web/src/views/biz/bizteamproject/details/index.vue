<template>
	<a-space style="width: 100%" direction="vertical">
		<a-card
			:bordered="false"
			style="width: 100%"
			@tabChange="(key) => onTabChange1(key)"
			:tab-list="tabListNoTitle1"
			:active-tab-key="currentTabKey"
			size="small"
		>
			<template #customTab="item">
				<a-button size="small" type="text">{{ item.tab }}</a-button>
			</template>
			<template #tabBarExtraContent>
				<!--				<a href="#">More</a>-->
			</template>
		</a-card>

		<a-row style="padding: 10px" v-show="currentTabKey === 'info'" :gutter="24">
			<a-col :span="14">
				<a-space direction="vertical" style="width: 100%">
					<a-card style="padding: 5px">
						<a-form layout="horizontal">
							<a-form-item>
								<a-row align="middle">
									<a-space>
										<a-col>
											<a-config-provider
												:theme="{
													token: {
														colorPrimaryBg: modelRef.statusColor,
														colorPrimary: modelRef.statusColor,
														colorBgBase: modelRef.statusColor,
														colorTextBase: '#fff',
														colorBorder: modelRef.statusColor
													}
												}"
											>
												<a-select v-model:value="modelRef.status" style="width: 120px" :options="statusList">
													<template #suffixIcon>
														<smile-outlined class="ant-select-suffix" />
													</template>
												</a-select>
											</a-config-provider>
										</a-col>
										<a-col>
											<a-radio-group v-model:value="modelRef.statusColor">
												<a-config-provider
													v-for="item in statusColor"
													:theme="{ token: { colorPrimary: item, colorBorder: item } }"
												>
													<a-radio :value="item"></a-radio>
												</a-config-provider>
											</a-radio-group>
										</a-col>
									</a-space>
								</a-row>
							</a-form-item>
							<a-form-item
								:label-col="{ span: 6 }"
								:wrapper-col="{ span: 24 }"
								name="contentText"
								v-bind="validateInfos.contentText"
							>
								<a-mentions
									v-model:value="modelRef.contentText"
									rows="3"
									placeholder="请输入信息,支持@成员"
									:options="mentionsOptions"
								></a-mentions>
							</a-form-item>
							<a-form-item :wrapper-col="{ span: 12, offset: 0 }">
								<a-button :loading="commentAddLoading" type="primary" @click="addComment">提交 </a-button>
								<a-button style="margin-left: 8px" @click="resetFields">重置</a-button>
							</a-form-item>
						</a-form>
					</a-card>
					<a-card title="时间轴">
						<!--						<template #extra><a>详情</a></template>-->
						<a-timeline :reverse="true" :pending="commentPageLoading ? '加载中...' : ''">
							<a-timeline-item :color="item.statusColor" v-for="item in commentRecords">
								<a-comment>
									<!--									<template #actions>-->
									<!--										<span v-if="!item.showReplyComment" @click="showReplyComment(item)" key="comment-basic-reply-to"-->
									<!--											>回复-->
									<!--										</span>-->
									<!--										<span-->
									<!--											v-if="item.bizTeamProjectCommentReplies.length"-->
									<!--											key="comment-basic-reply-to"-->
									<!--											@click="changeReply(item)"-->
									<!--											>{{ item.showReply ? '收起' : '展开' }}-->
									<!--											{{-->
									<!--												item.bizTeamProjectCommentReplies.length-->
									<!--													? '+' + item.bizTeamProjectCommentReplies.length + ''-->
									<!--													: ''-->
									<!--											}}-->
									<!--										</span>-->
									<!--									</template>-->
									<template #author>
										<a-tag :color="item.statusColor" style="color: #fff">{{ item.status }}</a-tag>
										<a> {{ item.createUserName }} {{ item.createTime }} </a>
									</template>
									<template #avatar>
										<a-avatar :src="item.avatar" :alt="item.createUserName" />
									</template>
									<template #content>
										<p v-html="highlightedText(item.contentText)"></p>
										<a-comment v-if="item.showReplyComment">
											<template #avatar>
												<!--												<a-avatar src="https://joeschmoe.io/api/v1/random" alt="Han Solo" />-->
												<!--										-->
											</template>
											<template #content>
												<a-form :model="replyForm">
													<a-form-item required name="contentText">
														<a-textarea :rows="4" v-model:value="replyForm.contentText" />
													</a-form-item>
													<a-form-item>
														<a-space>
															<a-button
																:loading="submitReplyLoading"
																@click="submitReply(item)"
																html-type="submit"
																type="primary"
																>添加回复
															</a-button>
															<a-button html-type="submit" @click="showReplyComment({})"> 取消 </a-button>
														</a-space>
													</a-form-item>
												</a-form>
											</template>
										</a-comment>
									</template>

									<template v-if="item.showReply">
										<a-comment v-for="reply in item.bizTeamProjectCommentReplies" :key="item.id">
											<template #author
												><a>{{ reply.createUserName }}</a></template
											>
											<template #avatar>
												<a-avatar :src="reply.avatar" :alt="reply.createUserName" />
											</template>
											<template #content>
												<p>
													{{ reply.contentText }}
												</p>
											</template>
											<template #datetime>
												<a-tooltip :title="dayjs(reply.createTime).format('YYYY-MM-DD HH:mm:ss')">
													<span>{{ dayjs(reply.createTime).fromNow() }}</span>
												</a-tooltip>
											</template>
										</a-comment>
									</template>
								</a-comment>
							</a-timeline-item>
						</a-timeline>
					</a-card>
				</a-space>
			</a-col>
			<a-col :span="10">
				<a-space style="width: 100%" direction="vertical">
					<a-card>
						<a-descriptions
							:column="{ xxl: 2, xl: 1, lg: 1, md: 1, sm: 1, xs: 1 }"
							size="small"
							bordered
							:title="projectDetail.name"
						>
							<template #extra>
								<a-space v-if="permissionCode['delProject']">
									<a-popconfirm
										v-if="!isEdit"
										title="确认删除？"
										ok-text="确认"
										cancel-text="取消"
										@confirm="deleteBizTeamProject(projectDetail)"
									>
										<a-button danger>删除</a-button>
									</a-popconfirm>

									<a-button v-if="!isEdit" type="primary" @click="openEdit">修改</a-button>
									<template v-else>
										<a-button type="link" @click="saveEdit">保存</a-button>
										<a-popconfirm title="确认取消修改？" ok-text="确认" cancel-text="取消" @confirm="cancelEdit">
											<a href="#">取消</a>
										</a-popconfirm>
									</template>
								</a-space>
							</template>
							<a-descriptions-item label="主办者">
								<a-avatar :src="projectDetail.avatar"></a-avatar>
								{{ projectDetail.createUserName }}
							</a-descriptions-item>
							<a-descriptions-item label="创建时间">{{ projectDetail.createTime }}</a-descriptions-item>
							<a-descriptions-item label="描述">
								<template v-if="!isEdit">
									{{ projectDetail.description }}
								</template>
								<a-textarea v-else v-model:value="editForm.description" placeholder="事项描述" :rows="4" />
							</a-descriptions-item>
							<a-descriptions-item label="更新时间">
								{{ projectDetail.updateTime ? projectDetail.updateTime : '--' }}
							</a-descriptions-item>
						</a-descriptions>
					</a-card>
					<a-card :tab-list="tabListNoTitle" :active-tab-key="noTitleKey" @tabChange="onTabChange" style="width: 100%">
						<template #tabBarExtraContent>
							<a v-if="noTitleKey === 'leader' || noTitleKey === 'manager'" @click="openAddUser('MANAGE')"
								>添加管理员</a
							>
							<a v-else-if="permissionCode['addUser']" @click="openAddUser('MEMBER')">邀请成员</a>
						</template>
						<a-avatar-group v-if="noTitleKey === 'all'">
							<a-avatar-group v-if="teamUser.length > 0">
								<a-tooltip v-for="item in teamUser" :title="item.headName" placement="top">
									<a-avatar :src="item.avatar"></a-avatar>
								</a-tooltip>
							</a-avatar-group>
							<a-empty v-else />
						</a-avatar-group>
						<a-avatar-group v-else-if="noTitleKey === 'leader'">
							<a-tooltip v-for="item in leaderUser" :title="item.headName" placement="top">
								<a-avatar :src="item.avatar"></a-avatar>
							</a-tooltip>
						</a-avatar-group>
						<template v-else>
							<a-avatar-group v-if="managerUser.length > 0">
								<a-tooltip v-for="item in managerUser" :title="item.headName" placement="top">
									<a-avatar :src="item.avatar"></a-avatar>
								</a-tooltip>
							</a-avatar-group>
							<a-empty v-else />
						</template>
					</a-card>

					<!--					<a-card-->
					<!--						title="任务列表"-->
					<!--						:tab-list="taskCategory"-->
					<!--						:active-tab-key="taskKey"-->
					<!--						@tabChange="-->
					<!--							(key) => {-->
					<!--								taskKey = key-->
					<!--							}-->
					<!--						"-->
					<!--						style="width: 100%"-->
					<!--					>-->
					<!--						<template #extra>-->
					<!--							<a-button @click="openAddTaskForm">新增任务</a-button>-->
					<!--						</template>-->
					<!--						<a-list title="任务列表" style="width: 100%" :grid="{ gutter: 0, column: 1 }" :data-source="taskList">-->
					<!--							<template #renderItem="{ item }">-->
					<!--								<a-list-item style="padding: 0">-->
					<!--									<a-card style="width: 100%">{{ item.contentText }}</a-card>-->
					<!--								</a-list-item>-->
					<!--							</template>-->
					<!--						</a-list>-->
					<!--					</a-card>-->

					<!--					<a-card style="width: 100%" title="管理员">-->
					<!--						<template #extra><a @click="openAddUser('MANAGE')">添加管理员</a></template>-->
					<!--						<a-avatar-group v-if="managerUser.length > 0">-->
					<!--							<a-tooltip v-for="item in managerUser" :title="item.headName" placement="top">-->
					<!--								<a-avatar :src="item.avatar"></a-avatar>-->
					<!--							</a-tooltip>-->
					<!--						</a-avatar-group>-->
					<!--						<a-empty v-else />-->
					<!--					</a-card>-->
					<!--					<a-card style="width: 100%" title="参与者">-->
					<!--						<template #extra><a @click="openAddUser('MEMBER')">邀请成员</a></template>-->
					<!--						<a-avatar-group v-if="teamUser.length > 0">-->
					<!--							<a-tooltip v-for="item in teamUser" :title="item.headName" placement="top">-->
					<!--								<a-avatar :src="item.avatar"></a-avatar>-->
					<!--							</a-tooltip>-->
					<!--						</a-avatar-group>-->
					<!--						<a-empty v-else />-->
					<!--					</a-card>-->
				</a-space>
			</a-col>
		</a-row>
	</a-space>
	<template v-if="currentTabKey === 'task-list'">
		<taskListView></taskListView>
	</template>

	<addUserForm @successful="loadTeamUser" ref="addUserFormRef" />
	<addTaskForm ref="addTaskFormRef" />
</template>
<script name="bizteamprojectdetails" setup lang="js">
	import '@wangeditor/editor/dist/css/style.css' // 引入 css
	import { useRoute, useRouter } from 'vue-router'
	import { useLoading } from '@/composables/useLoading'
	import bizTeamProjectApi from '@/api/biz/bizTeamProjectApi'
	import { cloneDeep } from 'lodash-es'
	import addUserForm from './addUserForm.vue'
	import { useTemplateRef } from 'vue'
	import taskListView from './task/index.vue'

	import addTaskForm from '@/views/biz/bizteamproject/details/task/addTaskForm.vue'
	import dayjs from '@/utils/dayjs'
	import {
		useProjectInfo,
		useTeamProjectComment,
		useTeamProjectReply,
		useTeamProjectUser
	} from '@/views/biz/bizteamproject/composables'
	import bizTeamProjectTaskApi from '@/api/biz/bizTeamProjectTaskApi'

	const route = useRoute()

	const currentTabKey = ref('info')
	const tabListNoTitle1 = [
		{
			key: 'info',
			tab: '基本信息'
		},
		{
			key: 'task-list',
			tab: '任务列表'
		}
	]
	const onTabChange1 = (value) => {
		currentTabKey.value = value
	}
	//初始化数据
	const { projectDetail, currentProjectUser, load: projectInit } = useProjectInfo()

	const permissionCode = computed(() => {
		let array = currentProjectUser.value.permissionCode ? currentProjectUser.value.permissionCode : []
		let map = {}
		array.forEach((item) => {
			return (map[item] = true)
		})
		return map
	})

	//修改开始
	const isEdit = ref(false)
	const editForm = ref({})
	const openEdit = () => {
		editForm.value = cloneDeep(projectDetail.value)
		isEdit.value = true
	}
	const cancelEdit = () => {
		isEdit.value = false
	}

	const saveEdit = async () => {
		await bizTeamProjectApi.bizTeamProjectSubmitForm(
			{
				description: editForm.value.description,
				id: projectDetail.value.id
			},
			true
		)
		projectDetail.value.description = editForm.value.description
		isEdit.value = false
	}

	//团队人员

	const {
		tabListNoTitle,
		noTitleKey,
		onTabChange,
		teamUser,
		leaderUser,
		managerUser,
		memberUser,
		loadTeamUser,
		addUserFormRef,
		openAddUser
	} = useTeamProjectUser()

	//评论相关
	const {
		statusColor,
		statusList,
		modelRef,
		mentionsOptions,
		validateInfos,
		resetFields,
		commentAddLoading,
		addComment,
		commentPageLoading,
		commentRecords,
		loadFirstComment,
		highlightedText
	} = useTeamProjectComment(teamUser, currentProjectUser)

	const { load, loading, error } = useLoading(async () => {
		await projectInit()
		await loadTeamUser()
		await loadFirstComment()
	})

	const { showReplyComment, replyForm, changeReply, submitReply, submitReplyLoading } = useTeamProjectReply(
		teamUser,
		currentProjectUser,
		commentRecords
	)

	//任务相关

	const addTaskFormRef = useTemplateRef('addTaskFormRef')
	const openAddTaskForm = () => {
		const id = route.query.id
		addTaskFormRef.value.onOpen({
			teamProjectId: id
		})
	}
	//任务列表
	const taskList = ref([{}])
	const taskCategory = [
		{
			key: 'all',
			tab: '全部'
		},
		{
			key: 'pending',
			tab: '待办'
		},
		{
			key: 'cancelled',
			tab: '作废'
		},
		{
			key: 'completed',
			tab: '完成'
		}
	]

	const router = useRouter()
	const taskKey = ref('all')
	// 删除
	const deleteBizTeamProject = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizTeamProjectApi.bizTeamProjectDelete(params).then(() => {
			router.push({ name: 'bizteamproject' })
		})
	}
	const {
		load: loadTaskList,
		loading: loadingTask,
		error: taskError
	} = useLoading(async () => {
		taskList.value = await bizTeamProjectTaskApi.bizTeamProjectTaskList({
			teamProjectId: route.query.id
		})
	})
	loadTaskList().then()

	onMounted(() => {
		load()

		if (route.query.taskid) {
			currentTabKey.value = 'task-list'
		}
	})
</script>

<style scoped></style>
