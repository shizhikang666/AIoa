<template>
	<xn-form-container
		:is-use-modal="true"
		:width="1000"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose('edit')"
	>
		<a-row :gutter="24">
			<a-col span="11">
				<a-skeleton active :loading="loading">
					<a-form :model="info">
						<a-form-item name="contentText">
							<a-textarea
								class="title-textarea"
								size="large"
								:bordered="false"
								autoSize
								@focus="onInputFocus"
								v-model:value="info.contentText"
								@focusout="editTaskContentText"
							></a-textarea>
						</a-form-item>

						<a-form-item name="status">
							<template #label>
								<a-space class="secondary-text">
									<CarryOutOutlined />
									<span> 状态 </span>
								</a-space>
							</template>
							<a-tag :color="info.status === 'COMPLETE' ? 'green' : ''">
								<a-config-provider
									:theme="{
										token: {
											colorPrimary: '#389e0d'
										}
									}"
								>
									<a-checkbox
										size="small"
										@click="editTaskStatus(info)"
										:checked="info.status === 'COMPLETE'"
									></a-checkbox>
								</a-config-provider>

								{{ info.status === 'COMPLETE' ? '已完成' : '未完成' }}
								<!--							<template #icon>-->
								<!--								<check-circle-outlined v-if="info.status === 'COMPLETE'" />-->
								<!--							</template>-->
							</a-tag>
						</a-form-item>

						<a-form-item name="user">
							<template #label>
								<a-space class="secondary-text">
									<UserOutlined />
									<span> 执行者 </span>
								</a-space>
							</template>
							<a-space>
								<xn-user-selector
									@onBack="onSelectUser"
									:user-name-show="false"
									:org-tree-api="selectorApiFunction.orgTreeApi"
									:user-page-api="loadUsers"
									:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
									data-type="objects"
									:userShow="true"
									v-model:value="info.users"
								/>
							</a-space>
						</a-form-item>

						<a-form-item name="">
							<template #label>
								<a-space class="secondary-text">
									<RightSquareOutlined />
									<span> 进度 </span>
								</a-space>
							</template>

							<a-space>
								<div style="width: 170px">
									<a-progress size="small" :percent="info.progress" />
								</div>
								<a-button-group>
									<a-button @click="calcProgress(-20)">
										<template #icon>
											<minus-outlined />
										</template>
									</a-button>
									<a-button @click="calcProgress(20)">
										<template #icon>
											<plus-outlined />
										</template>
									</a-button>
								</a-button-group>
							</a-space>
						</a-form-item>

						<a-form-item name="">
							<template #label>
								<a-space class="secondary-text">
									<FieldTimeOutlined />
									<span> 创建时间 </span>
								</a-space>
							</template>
							<div style="width: 170px">
								{{ info.createTime }}
							</div>
						</a-form-item>
					</a-form>
				</a-skeleton>
			</a-col>
			<a-divider style="height: 600px" type="vertical"></a-divider>
			<a-col span="12">
				<div style="display: flex; flex-direction: column; height: 600px; justify-content: space-between">
					<div class="header">
						<a-space>
							<span>评论</span>
							<div>
								<a-dropdown>
									<template #overlay>
										<a-menu>
											<a-menu-item key="1">
												<a-popconfirm title="确定删除此任务？" @confirm="removeTask">
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
						</a-space>
						<a-divider />
					</div>
					<a-row ref="scrollContainer" style="overflow-y: auto; flex: 1">
						<div>
							<a-comment ref="commentListRef" v-for="item in commentList" style="width: 100%; padding: 0">
								<template #author v-if="item.category !== 'LOG'">
									{{ item.createUserName }}
								</template>
								<template #avatar>
									<a-avatar v-if="item.category !== 'LOG'" :src="item.avatar"></a-avatar>

									<EditOutlined v-else />
								</template>
								<template #content>
									<p class="text-12 opacity-60">
										{{ item.contentText }}
									</p>
									<a-space wrap v-if="item.category !== 'LOG'">
										<a-card
											hoverable
											:body-style="{
												padding: '5px 10px'
											}"
											size="small"
											v-for="(record, i) in item.files"
										>
											<a-flex justify="space-between" align="center">
												<a-space>
													<img
														:src="record.thumbnail"
														class="record-img"
														v-if="
															record.suffix === 'png' ||
															record.suffix === 'jpg' ||
															record.suffix === 'jpeg' ||
															record.suffix === 'ico' ||
															record.suffix === 'bmp' ||
															record.suffix === 'gif'
														"
													/>
													<img
														src="/src/assets/images/fileImg/docx.png"
														class="record-img"
														v-else-if="record.suffix === 'doc' || record.suffix === 'docx'"
													/>
													<img
														src="/src/assets/images/fileImg/xlsx.png"
														class="record-img"
														v-else-if="record.suffix === 'xls' || record.suffix === 'xlsx'"
													/>
													<img
														src="/src/assets/images/fileImg/zip.png"
														class="record-img"
														v-else-if="record.suffix === 'zip'"
													/>
													<img
														src="/src/assets/images/fileImg/rar.png"
														class="record-img"
														v-else-if="record.suffix === 'rar'"
													/>
													<img
														src="/src/assets/images/fileImg/ppt.png"
														class="record-img"
														v-else-if="record.suffix === 'ppt' || record.suffix === 'pptx'"
													/>
													<img
														src="/src/assets/images/fileImg/pdf.png"
														class="record-img"
														v-else-if="record.suffix === 'pdf'"
													/>
													<img
														src="/src/assets/images/fileImg/txt.png"
														class="record-img"
														v-else-if="record.suffix === 'txt'"
													/>
													<img
														src="/src/assets/images/fileImg/html.png"
														class="record-img"
														v-else-if="record.suffix === 'html'"
													/>
													<img src="/src/assets/images/fileImg/file.png" class="record-img" v-else />
													<a-typography-link :href="record.downloadPath">{{ record.name }} </a-typography-link>
												</a-space>
											</a-flex>
										</a-card>
									</a-space>
								</template>
								<!--									<template #datetime>-->
								<!--										&lt;!&ndash;										<a-tooltip :title="item.datetime.format('YYYY-MM-DD HH:mm:ss')">&ndash;&gt;-->
								<!--										&lt;!&ndash;											<span>{{ item.datetime.fromNow() }}</span>&ndash;&gt;-->
								<!--										&lt;!&ndash;										</a-tooltip>&ndash;&gt;-->
								<!--									</template>-->
							</a-comment>
						</div>
					</a-row>
					<div>
						<a-divider :style="{ marginBottom: formData.files ? '10px' : '0' }" />
						<a-row v-if="formData.files">
							<a-space wrap>
								<a-card
									:body-style="{
										padding: '5px 10px'
									}"
									size="small"
									v-for="(record, i) in formData.files"
								>
									<a-flex justify="space-between" align="center">
										<a-space>
											<img
												:src="record.thumbnail"
												class="record-img"
												v-if="
													record.suffix === 'png' ||
													record.suffix === 'jpg' ||
													record.suffix === 'jpeg' ||
													record.suffix === 'ico' ||
													record.suffix === 'bmp' ||
													record.suffix === 'gif'
												"
											/>
											<img
												src="/src/assets/images/fileImg/docx.png"
												class="record-img"
												v-else-if="record.suffix === 'doc' || record.suffix === 'docx'"
											/>
											<img
												src="/src/assets/images/fileImg/xlsx.png"
												class="record-img"
												v-else-if="record.suffix === 'xls' || record.suffix === 'xlsx'"
											/>
											<img
												src="/src/assets/images/fileImg/zip.png"
												class="record-img"
												v-else-if="record.suffix === 'zip'"
											/>
											<img
												src="/src/assets/images/fileImg/rar.png"
												class="record-img"
												v-else-if="record.suffix === 'rar'"
											/>
											<img
												src="/src/assets/images/fileImg/ppt.png"
												class="record-img"
												v-else-if="record.suffix === 'ppt' || record.suffix === 'pptx'"
											/>
											<img
												src="/src/assets/images/fileImg/pdf.png"
												class="record-img"
												v-else-if="record.suffix === 'pdf'"
											/>
											<img
												src="/src/assets/images/fileImg/txt.png"
												class="record-img"
												v-else-if="record.suffix === 'txt'"
											/>
											<img
												src="/src/assets/images/fileImg/html.png"
												class="record-img"
												v-else-if="record.suffix === 'html'"
											/>
											<img src="/src/assets/images/fileImg/file.png" class="record-img" v-else />

											<span>{{ record.name }}</span>
										</a-space>
										<div>
											<a-button type="text" @click="formData.files.splice(i, 1)">
												<DeleteOutlined />
											</a-button>
										</div>
									</a-flex>
								</a-card>
							</a-space>
						</a-row>
						<a-row>
							<a-comment style="width: 100%; padding-top: 0">
								<template #avatar>
									<a-avatar :src="userInfo.avatar" />
								</template>
								<template #content>
									<div direction="vertical" style="width: 100%">
										<a-form :model="formData" ref="commentFormRef" lazy-validation>
											<a-form-item name="contentText" :rules="[{ required: true, message: '请输入内容！' }]">
												<a-textarea show-count :maxlength="250" v-model:value="formData.contentText" :rows="4" />
												<a-space style="margin-top: 10px">
													<a-button @click="uploadFormRef.openUpload()" type="text">
														<LinkOutlined />
													</a-button>
													<a-popover v-model:open="showEmoji" trigger="click">
														<template #content>
															<EmojiPicker
																:static-texts="{
																	skinTone: '肤色'
																}"
																:disabled-groups="[
																	'animals_nature',
																	'food_drink',
																	'activities',
																	'travel_places',
																	'objects',
																	'symbols',
																	'flags'
																]"
																:hide-search="true"
																:hide-group-names="true"
																:hide-group-icons="true"
																:native="true"
																:theme="store.theme === 'realDark' ? 'dark' : 'light'"
																@select="onSelectEmoji"
															/>
														</template>
														<a-button type="text">
															<SmileOutlined />
														</a-button>
													</a-popover>
													<a-button :loading="submitLoading" @click="submitComment" type="primary"> 评论 </a-button>
												</a-space>
											</a-form-item>
										</a-form>
									</div>
								</template>
							</a-comment>
						</a-row>
					</div>
				</div>
			</a-col>
		</a-row>
		<template #footer></template>
	</xn-form-container>

	<uploadForm ref="uploadFormRef" @successful="addFile" />
</template>

<script setup name="taskDetail">
	import bizTeamProjectTaskApi from '@/api/biz/bizTeamProjectTaskApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useLoading } from '@/composables/useLoading'
	import { globalStore } from '@/store'
	import EmojiPicker from 'vue3-emoji-picker'

	// import css
	import 'vue3-emoji-picker/css'
	import UploadForm from '@/views/biz/file/uploadForm.vue'
	import { useTemplateRef } from 'vue'
	import BizTeamProjectCommentApi from '@/api/biz/bizTeamProjectCommentApi'
	import BizTeamProjectTaskApi from '@/api/biz/bizTeamProjectTaskApi'
	import bizTeamProjectTaskCommentApi from '@/api/biz/bizTeamProjectTaskCommentApi'
	import { cloneDeep, debounce } from 'lodash-es'
	import BizTeamProjectUserApi from '@/api/biz/bizTeamProjectUserApi'
	import { useRoute } from 'vue-router'
	import { safeJsonParse } from '@/utils/json'

	const route = useRoute()
	const uploadFormRef = useTemplateRef('uploadFormRef')
	const formData = ref({})
	const teamUser = ref([])

	const { load: loadTeam } = useLoading(async () => {
		const id = info.value.teamProjectId
		teamUser.value = await BizTeamProjectUserApi.bizTeamProjectUserList({
			id: id
		})
	})
	const loadUsers = async (param) => {
		return await selectorApiFunction.userPageApi(
			Object.assign(param, {
				userIdList: teamUser.value.map((v) => v.userId).join(',')
			})
		)
	}

	const onSelectUser = async (param) => {
		await bizTeamProjectTaskApi.bizTeamProjectTaskUserEdit({
			user: param,
			id: info.value.id
		})
	}

	const showEmoji = ref(false)

	const onSelectEmoji = (v) => {
		formData.value.contentText = formData.value.contentText ? formData.value.contentText + v.i : v.i
		showEmoji.value = false
	}
	const editTaskStatus = async (item) => {
		await bizTeamProjectTaskApi.bizTeamProjectTaskEdit({
			id: item.id,
			status: item.status === 'COMPLETE' ? 'TODO' : 'COMPLETE'
		})

		item.status = item.status === 'COMPLETE' ? 'TODO' : 'COMPLETE'
	}

	const baseInfo = ref('')
	const onInputFocus = () => {
		baseInfo.value = info.value.contentText
	}
	const editTaskContentText = async () => {
		if (baseInfo.value === info.value.contentText) {
			return
		}
		await bizTeamProjectTaskApi.bizTeamProjectTaskEdit({
			id: info.value.id,
			contentText: info.value.contentText
		})
	}

	const calcProgress = debounce(async (i) => {
		await bizTeamProjectTaskApi.bizTeamProjectTaskEdit({
			id: info.value.id,
			progress: info.value.progress + i
		})
		info.value.progress = info.value.progress + i
	}, 300)

	const addFile = (file) => {
		if (!formData.value.files) {
			formData.value.files = []
		}
		formData.value.files.push(file)
	}

	const store = globalStore()
	// 传递设计器需要的API
	const selectorApiFunction = useUserSelector()
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null, close: null })
	const userInfo = store.userInfo

	const taskId = ref('')
	const info = ref({})
	const commentList = ref([])
	const { loading, load, error } = useLoading(async (id) => {
		info.value = await bizTeamProjectTaskApi.bizTeamProjectTaskDetail({
			id
		})
		info.value.users = info.value.users.map((v) => {
			return v.userId
		})
	})
	const scrollContainer = useTemplateRef('scrollContainer')
	const { load: loadComment, loading: commentLoading } = useLoading(async () => {
		const res = await bizTeamProjectTaskCommentApi.bizTeamProjectTaskCommentList({
			teamProjectTaskId: taskId.value
		})
		commentList.value = res.map((v) => {
			const files = safeJsonParse(v.extJson, {}).file || []
			return {
				...v,
				files: files
			}
		})
	})

	const commentListRef = useTemplateRef('commentListRef')
	// 打开抽屉
	const onOpen = async (record) => {
		open.value = true
		taskId.value = record.id
		await load(record.id)
		await loadTeam()
		await loadComment()
		if (route.query.commentid) {
			const findindex = commentList.value.findIndex((v) => v.id === route.query.commentid)

			if (findindex >= 0) {
				await nextTick().then(() => {
					if (scrollContainer.value && commentListRef.value[findindex]) {
						const el = scrollContainer.value.$el
						const commentEl = commentListRef.value[findindex].$el
						el.scrollTo({
							top: commentEl.offsetTop,
							behavior: 'smooth' // 平滑滚动
						})
					}
				})
			}

			console.log(commentListRef)
		}
	}
	// 关闭抽屉
	const onClose = (type) => {
		open.value = false
		emit('close', info.value, type ? type : 'edit')
	}

	const commentFormRef = useTemplateRef('commentFormRef')
	const { loading: submitLoading, load: submitComment } = useLoading(async () => {
		await commentFormRef.value.validate()
		const form = cloneDeep(formData.value)

		await bizTeamProjectTaskCommentApi.bizTeamProjectTaskCommentSubmitForm({
			...form,
			teamProjectTaskId: taskId.value
		})

		formData.value = {}
		await loadComment()
		await nextTick().then(() => {
			if (scrollContainer.value) {
				const el = scrollContainer.value.$el
				el.scrollTo({
					top: el.scrollHeight,
					behavior: 'smooth' // 平滑滚动
				})
			}
		})
	})

	const { load: removeTask } = useLoading(async () => {
		await bizTeamProjectTaskApi.bizTeamProjectTaskDelete([
			{
				id: info.value.id
			}
		])
		onClose('delete')
	})

	// 抛出函数
	defineExpose({
		onOpen
	})
</script>

<style scoped lang="less">
	//::v-deep(.ant-comment-inner) {
	//	padding-bottom: 0;
	//}

	.record-img {
		width: 20px;
		height: 20px;
	}

	.title-textarea {
		//color: @text-color-secondary;
		&:hover {
			background: @divider-color;
		}

		&:focus-within {
			background: @divider-color;
		}
	}

	.secondary-text {
		//color: @text-color-secondary;
	}
</style>
