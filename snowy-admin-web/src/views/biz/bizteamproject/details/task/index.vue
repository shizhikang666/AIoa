<template>
	<div class="kanban-container">
		<div v-if="loading" style="display: flex; justify-content: center; align-items: center; height: 100%">
			<a-spin />
		</div>
		<vuescroll v-else :ops="scrollOptions">
			<div>
				<a-space
					align="start"
					:size="50"
					v-draggable="[
						categoryList,
						{
							animation: 150,
							ghostClass: 'ghost',
							handle: '.handle',
							onUpdate: onUpdateCategoryList
						}
					]"
					direction="horizontal"
				>
					<div class="col" v-for="(item, i) in categoryList" :key="item.id">
						<div class="s-tool-column-item">
							<div
								@click="stopEvent"
								@mouseover="stopEvent"
								@mouseout="stopEvent"
								@mousemove="stopEvent"
								@mousedown="stopEvent"
								class="handle s-tool-column-handle layout-items-center"
							>
								<a-typography-title :level="5"> {{ item.title }}</a-typography-title>
							</div>
							<div>
								<a-dropdown>
									<template #overlay>
										<a-menu>
											<a-menu-item key="1">
												<a-popconfirm title="确定删除此分类？" @confirm="removeCategory(item, i)">
													<a-button type="link" danger size="small">删除</a-button>
												</a-popconfirm>
											</a-menu-item>
										</a-menu>
									</template>
									<a-button type="text">
										<DashOutlined />
									</a-button>
								</a-dropdown>
							</div>
						</div>
						<a-space style="width: 258px" direction="vertical">
							<div v-if="item.loading" style="width: 100%; display: flex; align-items: center; justify-content: center">
								<a-spin></a-spin>
							</div>

							<template v-else>
								<a-button @click="openAddTaskForm(item)" style="width: 100%" :icon="h(PlusOutlined)" />
							</template>
							<task-item-list-view
								@open-detail="openTaskDetail"
								:category-id="item.id"
								v-model:list="item.tasks"
							></task-item-list-view>
						</a-space>
					</div>
					<div class="col">
						<a-popover placement="bottomLeft" v-model:open="visibleAddCategory" trigger="click">
							<template #content>
								<a-form ref="formRef" :model="addCategoryForm" :rules="formRules" layout="horizontal">
									<a-form-item label="任务目标：" name="title">
										<a-textarea v-model:value="addCategoryForm.title" placeholder="请输入任务目标" allow-clear />
									</a-form-item>
								</a-form>
								<a-row>
									<a-button style="margin-right: 8px" @click="visibleAddCategory = false">关闭 </a-button>
									<a-button type="primary" @click="onSubmit" :loading="submitLoading">确认</a-button>
								</a-row>
							</template>

							<a-button type="text" @click="openAddTaskCategory">
								<template #icon>
									<PlusOutlined />
								</template>
								添加分组
							</a-button>
						</a-popover>
					</div>
				</a-space>
			</div>
		</vuescroll>
	</div>
	<addTaskForm @successful="onAddTask" ref="addTaskFormRef" />
	<taskDetail @close="onDetailClose" ref="detailRef" />
</template>
<script setup name="taskListView">
	import vuescroll from 'vuescroll/dist/vuescroll-slide'
	import { useProjectInfo } from '@/views/biz/bizteamproject/composables'
	import { useLoading } from '@/composables/useLoading'
	import { cloneDeep } from 'lodash-es'
	import bizTeamProjectTaskApi from '@/api/biz/bizTeamProjectTaskApi'
	import { useTemplateRef } from 'vue'
	import { required } from '@/utils/formRules'
	import bizTeamProjectTaskCategoryApi from '@/api/biz/bizTeamProjectTaskCategoryApi'
	import { vDraggable } from 'vue-draggable-plus'
	import { VueDraggable } from 'vue-draggable-plus'
	import { PlusOutlined, SearchOutlined } from '@ant-design/icons-vue'
	import { h } from 'vue'
	import roleApi from '@/api/sys/roleApi'
	import AddTaskForm from './addTaskForm.vue'
	import TaskItemListView from '@/views/biz/bizteamproject/details/task/taskItemListView.vue'
	import TaskDetail from '@/views/biz/bizteamproject/details/task/taskDetail.vue'
	import { useRoute } from 'vue-router'

	const stopEvent = (event) => {
		event.stopPropagation() // 阻止事件冒泡
	}
	const scrollOptions = {
		vuescroll: {
			zooming: false,
			scroller: {
				bouncing: {
					top: 0,
					bottom: 0,
					left: 50,
					right: 50
				},
				/** Enable locking to the main axis if user moves only slightly on one of them at start */
				locking: true,
				/** Minimum zoom level */

				/** Multiply or decrease scrolling speed **/
				speedMultiplier: 1,
				/** This configures the amount of change applied to deceleration when reaching boundaries  **/
				penetrationDeceleration: 0.03,
				/** This configures the amount of change applied to acceleration when reaching boundaries  **/
				penetrationAcceleration: 0.08,
				/** Whether call e.preventDefault event when sliding the content or not */
				preventDefault: true,
				/** Whether call preventDefault when (mouse/touch)move*/
				preventDefaultOnMove: false,
				// whether to  disable scroller or not.
				disable: false
			}
		},
		scrollPanel: {
			speed: 5000
		},
		bar: {
			showDelay: 500,
			onlyShowBarOnScroll: false,
			keepShow: false,
			background: '#c1c1c1',
			opacity: 1,
			hoverStyle: false,
			specifyBorderRadius: false,
			minSize: false,
			size: '6px',
			disable: false
		}
	}
	const { currentProjectUser, projectDetail, load: loadProjectInfo } = useProjectInfo()
	// 默认要校验的
	const formRules = {
		title: [required('分组标题必填')]
	}
	const formRef = useTemplateRef('formRef')
	const visibleAddCategory = ref(false)
	const addCategoryForm = ref({
		title: ''
	})
	const onDetailClose = (res, type) => {
		categoryList.value.forEach((item, i) => {
			item.tasks.forEach((task, j) => {
				if (task.id === res.id) {
					if (type === 'edit') {
						categoryList.value[i].tasks[j] = res
					}
					if (type === 'delete') {
						categoryList.value[i].tasks.splice(j, 1)
					}
				}
			})
		})
	}

	const onAddTask = (res) => {
		categoryList.value.forEach((item, i) => {
			if (item.id === res.teamProjectTaskCategoryId) {
				categoryList.value[i].tasks.push(res)
			}
		})
	}
	const openAddTaskCategory = async () => {
		visibleAddCategory.value = false
	}
	const { load: onSubmit, loading: submitLoading } = useLoading(async () => {
		await formRef.value.validate()
		const formDataParam = cloneDeep(addCategoryForm.value)
		const item = await bizTeamProjectTaskCategoryApi.bizTeamProjectTaskCategorySubmitForm(
			{
				...formDataParam,
				teamProjectId: projectDetail.value.id
			},
			false
		)
		addCategoryForm.value = {
			title: ''
		}
		visibleAddCategory.value = false
		categoryList.value.push({
			...item,
			tasks: [],
			loading: false
		})
	})
	//数据分类
	const categoryList = ref([])
	const loadTaskCategory = async () => {
		const res = await bizTeamProjectTaskCategoryApi.bizTeamProjectTaskCategoryList({
			teamProjectId: projectDetail.value.id
		})

		categoryList.value = res.map((item) => {
			return {
				...item,
				tasks: [],
				loading: false
			}
		})
	}
	const { load, loading, error } = useLoading(async () => {
		await loadProjectInfo()
		await loadTaskCategory()
		loadAllTaskList().then()
	})
	const onUpdateCategoryList = () => {
		const param = categoryList.value.map((item) => {
			return {
				id: item.id
			}
		})
		bizTeamProjectTaskCategoryApi.bizTeamProjectTaskCategorySort(param)
	}

	// 删除分类
	const removeCategory = (item, index) => {
		let params = [
			{
				id: item.id
			}
		]
		bizTeamProjectTaskCategoryApi.bizTeamProjectTaskCategoryDelete(params)
		categoryList.value.splice(index, 1)
	}

	// 任务列表
	const taskList = ref([])
	const addTaskFormRef = useTemplateRef('addTaskFormRef')
	const openAddTaskForm = (item) => {
		addTaskFormRef.value.onOpen({
			teamProjectId: projectDetail.value.id,
			teamProjectTaskCategoryId: item.id
		})
	}

	const loadAllTaskList = async () => {
		const map = {}
		categoryList.value.forEach((item, index) => {
			item.loading = true
			map[item.id] = index
		})
		try {
			const res = await bizTeamProjectTaskApi.bizTeamProjectTaskList({
				teamProjectId: projectDetail.value.id
			})
			res.forEach((item) => {
				const index = map[item.teamProjectTaskCategoryId]
				if (index >= 0) {
					categoryList.value[index].tasks.push(item)
				}
			})
		} finally {
			categoryList.value.forEach((item) => {
				item.loading = false
			})
		}
	}
	const detailRef = useTemplateRef('detailRef')
	const openTaskDetail = (item) => {
		detailRef.value.onOpen(item)
	}

	const editTaskStatus = async (item) => {
		await bizTeamProjectTaskApi.bizTeamProjectTaskEdit({
			id: item.id,
			status: item.status === 'COMPLETE' ? 'TODO' : 'COMPLETE'
		})

		item.status = item.status === 'COMPLETE' ? 'TODO' : 'COMPLETE'
	}

	const loadCategoryTaskList = async (item) => {
		const res = bizTeamProjectTaskApi.bizTeamProjectTaskList({
			teamProjectId: projectDetail.value.id,
			teamProjectTaskCategoryId: item.id
		})
		// categoryList.value.find((v) => v.id === item.id)
	}

	const route = useRoute()

	watchEffect(async () => {
		const query = route.query
		await nextTick()

		if (query.taskid) {
			openTaskDetail({
				id: query.taskid
			})
		}
		// 在这里可以执行其他操作，比如根据新的 ID 获取数据

		route.query.taskid = ''
	})

	load().then((v) => {})
</script>
<style lang="less" scoped>
	.s-tool-column-item {
		display: flex;
		align-items: center;
		padding: 4px 16px 4px 4px;
		min-width: 258px;
		max-width: 258px;
		justify-content: space-between;

		.s-tool-column-handle {
			opacity: 0.8;
			cursor: move;

			.anticon-more {
				font-size: 12px;

				& + .anticon-more {
					margin: 0px 4px 0 -8px;
				}
			}
		}
	}

	.kanban-row {
		display: flex;
	}

	.kanban-container {
		width: 100%;
		position: relative;
		overflow: auto;
		padding-left: 10px;
		//height: calc(100% - 129px);
		height: calc(100%);
	}
</style>
