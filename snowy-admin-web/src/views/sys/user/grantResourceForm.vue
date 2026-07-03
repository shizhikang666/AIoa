<template>
	<xn-form-container
		title="授权资源"
		:width="drawerWidth"
		:visible="visible"
		:destroy-on-close="true"
		:show-pagination="false"
		@close="onClose"
	>
		<a-alert message="非超管角色用户不可被授权系统模块菜单资源" banner class="mb-2" />
		<a-spin :spinning="spinningLoading">
			<a-radio-group v-model:value="moduleId" button-style="solid" class="xn-pb10">
				<a-radio-button
					:key="module.id"
					v-for="module in echoDatalist"
					:value="module.id"
					@click="moduleClock(module.id)"
				>
					<component :is="module.icon" />
					{{ module.title }}</a-radio-button
				>
			</a-radio-group>

			<a-table size="middle" :columns="columns" :data-source="loadDatas" :pagination="false" bordered>
				<template #bodyCell="{ column, record }">
					<template v-if="column.dataIndex === 'parentName'">
						<a-checkbox :checked="record.parentCheck" @update:checked="(val) => changeParent(record, val)">
							{{ record.parentName }}
						</a-checkbox>
					</template>

					<template v-if="column.dataIndex === 'title'">
						<a-checkbox :checked="record.nameCheck" @update:checked="(val) => changeSub(record, val)">{{
							record.title
						}}</a-checkbox>
					</template>

					<template v-if="column.dataIndex === 'button'">
						<template v-if="record.button.length > 0">
							<template v-for="(item, index) in record.button" :key="item.id">
								<a-checkbox v-model:checked="item.check" @change="(evt) => changeChildCheckBox(record, evt)">{{
									item.title
								}}</a-checkbox>
								<br v-if="(index + 1) % 5 === 0" />
							</template>
						</template>
					</template>
				</template>
			</a-table>
		</a-spin>
		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" :loading="submitLoading" @click="onSubmit">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="grantResourceForm">
	import { nextTick } from 'vue'
	import tool from '@/utils/tool'
	import userApi from '@/api/sys/userApi'
	import roleApi from '@/api/sys/roleApi'
	import userCenterApi from '@/api/sys/userCenterApi'
	const spinningLoading = ref(false)
	const firstShowMap = ref({})
	const emit = defineEmits({ successful: null })
	const submitLoading = ref(false)
	// 抽屉的宽度
	const drawerWidth = 1000
	// 自动获取宽度，默认获取浏览器的宽度的90%
	//(window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth) * 0.9

	const columns = [
		{
			key: 'parentName',
			title: '一级目录',
			dataIndex: 'parentName',
			customCell: (row, index) => {
				const groupKey = getMenuGroupKey(row)
				const indexArr = firstShowMap.value[groupKey] || []
				if (indexArr.length === 0 || index === indexArr[0]) {
					return { rowSpan: indexArr.length || 1 }
				}
				return { rowSpan: 0 }
			},
			width: 150
		},
		{
			key: 'title',
			title: '菜单',
			dataIndex: 'title',
			width: 200
		},
		{
			key: 'button',
			title: '按钮授权',
			dataIndex: 'button'
		}
	]
	const echoDatalist = ref([])
	const moduleId = ref('')
	const loadDatas = ref([])

	// 获取数据
	const loadData = async () => {
		// firstShowMap = {} // 重置单元格合并映射
		// 如果有数据，我们再不去反复的查询
		if (echoDatalist.value.length > 0) {
			const module = echoDatalist.value.find((f) => f.id === moduleId.value)
			loadDatas.value = module?.menu || []
			firstShowMap.value = buildFirstShowMap(loadDatas.value)
		} else {
			// 获取表格数据
			spinningLoading.value = true
			const res = await roleApi.roleResourceTreeSelector()
			const param = {
				id: resultDataModel.id
			}
			// 获取回显数据
			const resEcho = await userApi.userOwnResource(param)
			spinningLoading.value = false
			echoDatalist.value = echoModuleData(res, resEcho)
			const firstModule = echoDatalist.value[0] || {}
			moduleId.value = firstModule.id || ''
			loadDatas.value = firstModule.menu || []
			firstShowMap.value = buildFirstShowMap(loadDatas.value)
		}
	}
	const checkFieldKeys = ['button']
	const visible = ref(false)
	// 返回的数据模型，最终需要转换成这样
	let resultDataModel = {
		id: '',
		grantInfoList: []
	}
	// 打开抽屉
	const onOpen = (record) => {
		resultDataModel.id = record.id
		visible.value = true
		firstShowMap.value = {}
		loadData()
	}
	// 数据转换
	const getMenuGroupKey = (item) => item.parentKey || item.parentName || item.id
	const getSortValue = (value, fallback) => {
		const number = Number(value)
		return Number.isFinite(number) ? number : fallback
	}
	const normalizeMenuRows = (menu = []) => {
		const rows = Array.isArray(menu) ? menu : []
		const rowById = rows.reduce((map, item) => {
			if (item?.id) {
				map[item.id] = item
			}
			return map
		}, {})

		return rows
			.map((item, index) => {
				let parent = item
				let current = item
				const visited = new Set()
				while (current?.parentId && current.parentId !== '0' && rowById[current.parentId] && !visited.has(current.parentId)) {
					visited.add(current.parentId)
					current = rowById[current.parentId]
					parent = current
				}

				return {
					...item,
					parentKey: item.parentKey || parent?.id || item.id,
					parentName: item.parentName || parent?.title || item.title,
					parentSortCode: item.parentSortCode ?? parent?.sortCode ?? item.sortCode,
					_originIndex: index
				}
			})
			.sort((a, b) => {
				return (
					getSortValue(a.parentSortCode, a._originIndex) - getSortValue(b.parentSortCode, b._originIndex) ||
					String(getMenuGroupKey(a)).localeCompare(String(getMenuGroupKey(b))) ||
					getSortValue(a.sortCode, a._originIndex) - getSortValue(b.sortCode, b._originIndex) ||
					a._originIndex - b._originIndex
				)
			})
	}
	const buildFirstShowMap = (menu) => {
		return menu.reduce((map, item, index) => {
			const groupKey = getMenuGroupKey(item)
			if (map[groupKey]) {
				map[groupKey].push(index)
			} else {
				map[groupKey] = [index]
			}
			return map
		}, {})
	}
	const echoModuleData = (data, resEcho) => {
		// 通过应用循环
		data.forEach((module) => {
			if (module.menu) {
				// 加入回显内容
				module.menu.forEach((item) => {
					const menueCheck = ref(0)
					if (resEcho.grantInfoList.length > 0) {
						resEcho.grantInfoList.forEach((grant) => {
							if (item.id === grant.menuId) {
								menueCheck.value++
								// 处理按钮
								if (grant.buttonInfo.length > 0) {
									grant.buttonInfo.forEach((button) => {
										item.button.forEach((itemButton) => {
											if (button === itemButton.id) {
												itemButton.check = true
											}
										})
									})
								}
							}
						})
					}
					// 回显前面的2个
					if (menueCheck.value > 0) {
						item.parentCheck = true
						item.nameCheck = true
					}
				})

				// 排序
				module.menu = normalizeMenuRows(module.menu)
				// 缓存加入索引
				module.menu.forEach((item, index) => {
					// 下面就是用来知道不同的一级菜单里面有几个二级菜单，以及他们所在的索引
					const groupKey = getMenuGroupKey(item)
					if (firstShowMap.value[groupKey]) {
						firstShowMap.value[groupKey].push(index)
					} else {
						firstShowMap.value[groupKey] = [index]
					}
				})
			}
		})
		return data
	}

	// 通过应用分菜单
	const moduleClock = (value) => {
		moduleId.value = value
		loadData()
	}
	// 遍历字段
	const handleOnlySelf = (record, key, val) => {
		record[key].forEach((item) => {
			// 处理'button'选中状态
			item.check = val
		})
	}
	const checkAllChildNotChecked = (record) => {
		const allChecked = checkFieldKeys.every((key) => {
			// 遍历所有的字段
			const child = record[key]
			return child.every((field) => !field.check)
		})
		return allChecked
	}
	const changeChildCheckBox = (record, evt) => {
		let checked = evt.target.checked
		if (!checked && checkAllChildNotChecked(record)) {
			// 这里注释掉勾选去掉所有按钮，联动去掉菜单
			/*record.nameCheck = false
			record.parentCheck = false*/
		} else if (checked) {
			record.nameCheck = checked
			record.parentCheck = checked
		}
	}
	// 二级菜单的勾选
	const changeSub = (record, val) => {
		// 选中二级菜单
		record.nameCheck = val
		checkFieldKeys.forEach((key) => {
			// 遍历所有的字段
			handleOnlySelf(record, key, val)
		})
	}
	// 当点击首列的勾选
	const changeParent = (record, val) => {
		record.parentCheck = val
		// 通过这个应用id，找到应用下的所有菜单
		const moduleMenu = echoDatalist.value.find((f) => record.module === f.id)
		// 获取同一级菜单的所有索引
		const indexArr = firstShowMap.value[getMenuGroupKey(record)] || []
		indexArr.forEach((indexItem) => {
			// 获取同一级菜单的所有行
			const row = moduleMenu?.menu?.[indexItem]
			// 给这些菜单的索引去勾选
			if (row) {
				changeSub(row, val)
			}
		})
	}
	// 关闭抽屉
	const onClose = () => {
		// 将这些缓存的给清空
		echoDatalist.value = []
		moduleId.value = ''
		loadDatas.value = []
		firstShowMap.value = {}
		visible.value = false
	}
	// 提交之前转换数据
	const convertData = () => {
		resultDataModel.grantInfoList = []
		echoDatalist.value.forEach((table) => {
			if (table.menu) {
				table.menu.forEach((item) => {
					const grantInfo = {
						menuId: '',
						buttonInfo: []
					}
					if (item.nameCheck) {
						grantInfo.menuId = item.id
						item.button.forEach((button) => {
							if (button.check) {
								grantInfo.buttonInfo.push(button.id)
							}
						})
						resultDataModel.grantInfoList.push(grantInfo)
					}
				})
			}
		})
		return resultDataModel
	}
	// 验证并提交数据
	const onSubmit = () => {
		const param = convertData()
		submitLoading.value = true
		userApi
			.userGrantResource(param)
			.then(() => {
				onClose()
				emit('successful')
				refreshCacheMenu()
			})
			.finally(() => {
				submitLoading.value = false
			})
	}
	// 刷新缓存的菜单
	const refreshCacheMenu = () => {
		nextTick(() => {
			userCenterApi.userLoginMenu().then((res) => {
				tool.data.set('MENU', res)
			})
		})
	}
	// 调用这个函数将子组件的一些数据和方法暴露出去
	defineExpose({
		onOpen
	})
</script>

<style scoped>
	/* 重写复选框的样式 */
	.ant-checkbox-wrapper {
		margin-left: 0px !important;
		padding-top: 2px !important;
		padding-bottom: 2px !important;
	}
</style>
