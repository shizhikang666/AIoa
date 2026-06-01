import tool from '@/utils/tool'
import { computed } from 'vue'
import { cloneDeep, isArray } from 'lodash-es'
import { required } from '@/utils/formRules'

function mergeNonEmptyArrays(target, source, keys) {
	if (!source) {
		return
	}

	keys.forEach((key) => {
		if (target[key] === null || target[key] === undefined || target[key].length === 0) {
			if (isArray(target[key])) {
				if (isArray(source[key])) {
					target[key] = source[key]
				}
			} else {
				target[key] = source[key]
			}
		}
	})
}

export function useProcessParam(key) {
	const processConfigMap = cloneDeep(tool.data.get('SYS_CONFIG').processConfigMap)

	const sys_user_process_config = tool.data.get('SYS_USER_PROCESS_CONFIG')
		? cloneDeep(tool.data.get('SYS_USER_PROCESS_CONFIG').config)
		: []

	const find = sys_user_process_config.find((v) => v.processName === key)

	const object = processConfigMap[key]
		? processConfigMap[key]
		: {
				approveUserIdList: [],
				copyUserIdList: [],
				treasurer: '',
				procure: '',
				open: true
		  }

	mergeNonEmptyArrays(object, find ? find : {}, ['approveUserIdList', 'copyUserIdList', 'treasurer', 'procure'])

	const isOpenProcess = computed(() => {
		return object.open
	})

	const approveUserIdList = object.approveUserIdList
	const copyUserIdList = object.copyUserIdList
	const treasurer = object.treasurer
	const procure = object.procure
	const rule = isOpenProcess.value ? { approveUserIdList: [required('审批人不能为空')] } : {}

	return {
		isOpenProcess,
		approveUserIdList,
		copyUserIdList,
		treasurer,
		procure,
		rule
	}
}
