import BizTeamProjectUserApi from '@/api/biz/bizTeamProjectUserApi'
import { useTemplateRef } from 'vue'
import { Form, Mentions } from 'ant-design-vue'
import { useLoading } from '@/composables/useLoading'
import { cloneDeep } from 'lodash-es'
import BizTeamProjectCommentApi from '@/api/biz/bizTeamProjectCommentApi'
import bizTeamProjectCommentReplyApi from '@/api/biz/bizTeamProjectCommentReplyApi'
import { useRoute, useRouter } from 'vue-router'
import BizTeamProjectApi from '@/api/biz/bizTeamProjectApi'
import bizTeamProjectTaskCategoryApi from '@/api/biz/bizTeamProjectTaskCategoryApi'

export function useProjectInfo() {
	const projectDetail = ref({})
	const currentProjectUser = ref({})
	const route = useRoute()
	const load = async () => {
		const { project, user } = await BizTeamProjectApi.bizTeamProjectDetail({
			id: route.query.id
		})
		projectDetail.value = project

		currentProjectUser.value = user
	}

	return {
		load,
		projectDetail,
		currentProjectUser
	}
}

export function useTeamProjectUser() {
	const route = useRoute()
	const tabListNoTitle = [
		{
			tab: '参与者',
			key: 'all'
		}
		// {
		// 	tab: '群主',
		// 	key: 'leader'
		// },
		//
		// {
		// 	tab: '管理员',
		// 	key: 'manager'
		// }
	]
	const noTitleKey = ref('all')
	const onTabChange = (value) => {
		noTitleKey.value = value
	}

	const teamUser = ref([])
	const leaderUser = computed(() => {
		return teamUser.value.filter((v) => {
			return v.roleType === 'LEADER'
		})
	})
	const managerUser = computed(() => {
		return teamUser.value.filter((v) => {
			return v.roleType === 'MANAGE'
		})
	})
	const memberUser = computed(() => {
		return teamUser.value.filter((v) => {
			return v.roleType === 'MEMBER'
		})
	})
	const loadTeamUser = async () => {
		teamUser.value = await BizTeamProjectUserApi.bizTeamProjectUserList({
			id: route.query.id
		})
	}
	const addUserFormRef = useTemplateRef('addUserFormRef')
	const openAddUser = (role) => {
		const id = route.query.id
		addUserFormRef.value.onOpen({
			teamProjectId: id,
			roleType: role
		})
	}

	return {
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
	}
}

export function useTeamProjectComment(teamUser, currentProjectUser) {
	//评论相关
	const route = useRoute()
	const useForm = Form.useForm
	const statusList = ref([
		{
			value: '进度正常',
			label: '进度正常'
		},
		{
			value: '有风险',
			label: '有风险'
		},
		{
			value: '进度失控',
			label: '进度失控'
		}
	])
	const statusColor = ref(['#1677ff', '#52c41a', '#faad14', '#ff4d4f'])
	const modelRef = reactive({
		contentText: '',
		status: statusList.value[0].value,
		statusColor: statusColor.value[0]
	})
	const rulesRef = reactive({
		contentText: [
			{
				required: true,
				message: '请输入内容!'
			}
		]
	})

	const mentionsOptions = computed(() => {
		const defaultStyle = {}
		const result = teamUser.value
			.filter((v) => {
				return v.userId !== currentProjectUser.value.userId
			})
			.map((value) => {
				return {
					value: value.headName,
					label: value.headName,
					payload: value.userId,
					style: defaultStyle
				}
			})
		const all = result.map((value) => value.payload)
		result.unshift({
			value: '所有人',
			label: '所有人',
			payload: all
		})

		return result
	})

	const { getMentions } = Mentions

	const { resetFields, validate, validateInfos } = useForm(modelRef, rulesRef)
	const { loading: commentAddLoading, load: addComment } = useLoading(async (e) => {
		const id = route.query.id
		e.preventDefault()
		try {
			await validate()
		} catch (e) {
			return 'error'
		}
		const param = cloneDeep(modelRef)
		const uniqueArray = getMentions(modelRef.contentText).filter(
			(item, index, self) => index === self.findIndex((t) => t.value === item.value)
		)
		const mentionableUsers = []
		uniqueArray.forEach((item) => {
			const find = mentionsOptions.value.find((v) => v.value === item.value)
			if (!find) {
				return
			}

			if (find.payload instanceof Array) {
				mentionableUsers.push(...find.payload)
				return
			}

			mentionableUsers.push(find.payload)
		})
		await BizTeamProjectCommentApi.bizTeamProjectCommentAdd({
			teamProjectId: id,
			...param,
			mentionableUsers: mentionableUsers
		})
		resetFields()
		await loadFirstComment()
	})

	//加载最新的评论
	const commentRecords = ref([])
	const { loading: commentPageLoading, load: loadFirstComment } = useLoading(async () => {
		const id = route.query.id

		const res = await BizTeamProjectCommentApi.bizTeamProjectCommentList({
			teamProjectId: id,
			sortField: 'createTime'
		})

		commentRecords.value = res.map((value) => {
			return { ...value, showReplyComment: false, showReply: false }
		})
	})
	const highlightedText = (text) => {
		const urlRegex = /https?:\/\/[^\s]+/g
		return text.replace(urlRegex, (match) => {
			return `<a href="${match}"  target="_blank">${match}</a>`
		})
	}

	return {
		useForm,
		validateInfos,
		resetFields,
		validate,
		statusColor,
		statusList,
		modelRef,
		rulesRef,
		mentionsOptions,
		commentAddLoading,
		addComment,
		commentPageLoading,
		commentRecords,
		loadFirstComment,
		highlightedText
	}
}

export function useTeamProjectReply(teamUser, currentProjectUser, commentRecords) {
	//回复相关
	const replyForm = ref({
		contentText: ''
	})

	const showReplyComment = (item, type = 'form') => {
		replyForm.value.contentText = ''
		commentRecords.value.forEach((value) => {
			value.showReplyComment = false
			if (value.id === item.id) {
				value.showReplyComment = true
			}
		})
	}

	const changeReply = (item) => {
		commentRecords.value.forEach((value) => {
			if (value.id === item.id) {
				value.showReply = !value.showReply
			}
		})
	}

	const { load: submitReply, loading: submitReplyLoading } = useLoading(async (taget) => {
		if (!replyForm.value.contentText) {
			return
		}
		await bizTeamProjectCommentReplyApi.bizTeamProjectCommentReplySubmitForm({
			targetId: taget.id,
			contentText: replyForm.value.contentText
		})
		const find = commentRecords.value.find((v) => {
			return v.id === taget.id
		})
		if (find && find.bizTeamProjectCommentReplies) {
			find.bizTeamProjectCommentReplies.unshift({
				contentText: replyForm.value.contentText,
				id: Date.now(),
				createUserName: currentProjectUser.value.name,
				avatar: currentProjectUser.value.avatar,
				createTime: Date.now()
			})
		}
		replyForm.value.contentText = ''
	})

	return {
		showReplyComment,
		replyForm,
		changeReply,
		submitReply,
		submitReplyLoading
	}
}

export function useProjectTask() {
	const route = useRoute()
	const id = route.query.id
	const allTaskCategory = ref([])
	const load = async () => {
		const res = await bizTeamProjectTaskCategoryApi.bizTeamProjectTaskCategoryList({
			teamProjectId: id
		})
	}
}
