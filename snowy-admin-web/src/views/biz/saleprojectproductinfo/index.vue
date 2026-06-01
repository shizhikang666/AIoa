<template>
	<a-row :gutter="10">
		<a-col :xs="24" :sm="24" :md="24" :lg="6" :xl="6">
			<a-card :bordered="false" :loading="cardLoading">
				<a-tree
					v-if="treeData.length > 0"
					v-model:expandedKeys="treeDefaultExpandedKeys"
					:tree-data="treeData"
					:field-names="treeFieldNames"
					@select="treeSelect"
				>
				</a-tree>
				<a-empty v-else :image="Empty.PRESENTED_IMAGE_SIMPLE" />
			</a-card>
		</a-col>
		<a-col :xs="24" :sm="24" :md="24" :lg="18" :xl="18">
			<a-card>
				<a-space direction="vertical">
					<a-typography-title :level="5">软件产品出库表</a-typography-title>
					<a-segmented v-model:value="activeDateIndex" :options="dateOptions">
						<template #label="{ title }">
							<div style="padding: 4px 4px">
								<div>{{ title }}</div>
							</div>
						</template>
					</a-segmented>
					<a-button @click="exportData" :loading="exportLoading" type="primary">导出</a-button>
					<a-row :gutter="16"></a-row>
				</a-space>
				<a-skeleton v-if="!error" active :loading="loadingResult">
					<div style="display: block; padding: 10px">
						<a-table
							:rowExpandable="rowExpandableFilter"
							row-key="id"
							size="small"
							:data-source="productListData"
							:columns="columns"
						>
							<template #headerCell="{ column }">
								<template v-if="column.key === 'name'">
									<span style="color: #1890ff">产品名称</span>
								</template>
							</template>
							<template #customFilterDropdown="{ setSelectedKeys, selectedKeys, confirm, clearFilters, column }">
								<div style="padding: 8px">
									<a-input
										ref="searchInput"
										:placeholder="`搜索名字`"
										:value="selectedKeys[0]"
										style="width: 188px; margin-bottom: 8px; display: block"
										@change="(e) => setSelectedKeys(e.target.value ? [e.target.value] : [])"
										@pressEnter="handleSearch(selectedKeys, confirm, column.dataIndex)"
									/>
									<a-button
										type="primary"
										size="small"
										style="width: 90px; margin-right: 8px"
										@click="handleSearch(selectedKeys, confirm, column.dataIndex)"
									>
										<template #icon>
											<SearchOutlined />
										</template>
										搜索
									</a-button>
									<a-button size="small" style="width: 90px" @click="handleReset(clearFilters)"> 重置 </a-button>
								</div>
							</template>
							<template #customFilterIcon="{ filtered }">
								<search-outlined :style="{ color: filtered ? '#108ee9' : undefined }" />
							</template>
							<template #bodyCell="{ text, column, record }">
								<span v-if="state.searchText && state.searchedColumn === column.dataIndex">
									<template
										v-for="(fragment, i) in text
											.toString()
											.split(new RegExp(`(?<=${state.searchText})|(?=${state.searchText})`, 'i'))"
									>
										<mark v-if="fragment.toLowerCase() === state.searchText.toLowerCase()" :key="i" class="highlight">
											{{ fragment }}
										</mark>
										<template v-else>{{ fragment }}</template>
									</template>
								</span>
								<template v-if="column.dataIndex == 'projectName'">
									<a-typography-link @click="openDetail(record)">{{ record.projectName }} </a-typography-link>
								</template>

								<template v-if="column.key === 'operation'">
									<span class="table-operation">
										<a @click="openAddForm(record)">新增</a>
									</span>
								</template>
							</template>
							<template #expandedRowRender="{ record }">
								<a-table
									:loading="record.loading"
									:columns="innerColumns"
									:data-source="record.dataSource"
									:scroll="{ x: 1600 }"
									:pagination="false"
								>
									<template #bodyCell="chi">
										<template v-if="chi.column.key === 'operation'">
											<span class="table-operation">
												<a @click="handleEdit(chi.record)">编辑</a>
												<a-popconfirm
													title="确认要删除吗"
													placement="topRight"
													@confirm="removeInfo(record, chi.record)"
												>
													<a-button type="link" danger size="small">
														{{ $t('common.removeButton') }}
													</a-button>
												</a-popconfirm>
											</span>
										</template>
									</template>
								</a-table>
							</template>
						</a-table>
					</div>
				</a-skeleton>
			</a-card>
		</a-col>
	</a-row>
	<Form ref="formRef" @successful="loadingRowTable"></Form>
	<Detail ref="detailRef"></Detail>
</template>

<script setup name="saleprojectproductinfo">
	import { Empty } from 'ant-design-vue'
	import { useOrg } from '@/composables/useOrg'
	import { useLoading } from '@/composables/useLoading'
	import dayjs from '@/utils/dayjs'
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import bizProductApi from '@/api/biz/bizProductApi'
	import Form from '@/views/biz/saleprojectproductinfo/form.vue'
	import { useTemplateRef } from 'vue'
	import bizSaleProjectProductInfoApi from '@/api/biz/bizSaleProjectProductInfoApi'
	import Detail from '@/views/biz/saleproject/detail.vue'
	import ExcelJS from 'exceljs'
	import { saveAs } from 'file-saver'
	import { cloneDeep } from 'lodash-es'

	const formRef = useTemplateRef('formRef')

	//组织机构相关
	const { treeFieldNames, treeData, treeDefaultExpandedKeys, loadingTreeData } = useOrg()
	const { load, loading: cardLoading } = useLoading(loadingTreeData)
	const activeOrgId = ref('')

	const treeSelect = async (item) => {
		activeOrgId.value = item[0]
	}
	load()

	//日期筛选相关
	const activeDateIndex = ref(0)
	const dateOptions = ref([])
	const initDateOptions = () => {
		const currentYear = dayjs().year()
		// 创建一个数组来存储每个月的开始和结束时间
		const months = []
		for (let month = 0; month < 12; month++) {
			const startOfMonth = dayjs().year(currentYear).month(month).startOf('month')
			const endOfMonth = dayjs().year(currentYear).month(month).endOf('month')
			const currenMonth = month + 1
			months.push({
				value: month,
				title: `${currenMonth}月`,
				// 月份从 1 开始
				startCreateTime: startOfMonth.format('YYYY-MM-DD HH:mm:ss'),
				endCreateTime: endOfMonth.format('YYYY-MM-DD HH:mm:ss')
			})
		}
		// 获取今年的开始时间
		const startOfYear = dayjs().year(currentYear).startOf('year')
		// 获取今年的结束时间
		const endOfYear = dayjs().year(currentYear).endOf('year')

		const startCreateTime = startOfYear.format('YYYY-MM-DD HH:mm:ss')
		const endCreateTime = endOfYear.format('YYYY-MM-DD HH:mm:ss')
		// dateOptions.value.push({
		// 	title: '全年',
		// 	value: 0,
		// 	startCreateTime,
		// 	endCreateTime
		// })
		dateOptions.value.push(...months)
	}
	initDateOptions()

	//产品相关
	const productListData = ref([])
	const {
		loading: loadingResult,
		load: loadResult,
		error
	} = useLoading(async (param) => {
		// 1. 并行获取销售项目数据和产品列表
		const [result, productList] = await Promise.all([
			bizDataReportApi.bizSaleProjectDataListDetails(param),
			bizProductApi.bizProductList({ reconciliationType: 'ENABLE' })
		])

		// 2. 将产品列表转换为映射表
		const productMap = productList.reduce((map, item) => {
			map[item.id] = item
			return map
		}, {})

		// 3. 处理销售项目数据
		const list = result.flatMap((item) => {
			const baseInfo = {
				projectId: item.id,
				projectName: item.projectName,
				headName: item.headName,
				dataSource: [],
				loading: false
			}

			return item.productList.flatMap((product) => {
				const currentProduct = productMap[product.productId]

				// 处理子产品
				if (product.children?.length > 0) {
					return product.children
						.map((child) => {
							const childProductId = JSON.parse(child.extJson).product.id
							const childProduct = productMap[childProductId]
							return childProduct
								? {
										...baseInfo,
										id: child.id,
										reconciliationAmount: childProduct.reconciliationAmount,
										productId: childProduct.id,
										productName: childProduct.productName,
										totalNumber: product.number * child.number
								  }
								: null
						})
						.filter(Boolean) // 过滤掉无效的子产品
				}

				// 处理主产品
				return currentProduct
					? [
							{
								...baseInfo,
								reconciliationAmount: currentProduct.reconciliationAmount,
								id: product.id,
								productId: currentProduct.id,
								productName: currentProduct.productName,
								totalNumber: product.number
							}
					  ]
					: []
			})
		})

		// 4. 获取销售项目产品信息
		const targetIds = list.map((item) => item.id).join(',')
		const infos = await bizSaleProjectProductInfoApi.bizSaleProjectProductInfoList({ targetIds })

		// 5. 将产品信息关联到列表
		const infoMap = infos.reduce((map, info) => {
			if (!map[info.targetId]) {
				map[info.targetId] = []
			}
			map[info.targetId].push(info)
			return map
		}, {})

		list.forEach((item) => {
			item.dataSource = infoMap[item.id] || []
		})

		// 6. 更新数据
		productListData.value = list
	})
	const state = reactive({
		searchText: '',
		searchedColumn: ''
	})
	const searchInput = ref()
	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			key: 'productName',
			customFilterDropdown: true,
			onFilter: (value, record) => record.productName.toString().toLowerCase().includes(value.toLowerCase()),
			onFilterDropdownOpenChange: (visible) => {
				if (visible) {
					setTimeout(() => {
						searchInput.value.focus()
					}, 100)
				}
			}
		},
		{
			title: '项目名称',
			dataIndex: 'projectName',
			key: 'projectName',
			ellipsis: true,
			customFilterDropdown: true,
			onFilter: (value, record) => record.projectName.toString().toLowerCase().includes(value.toLowerCase()),
			onFilterDropdownOpenChange: (visible) => {
				if (visible) {
					setTimeout(() => {
						searchInput.value.focus()
					}, 100)
				}
			}
		},
		{
			title: '负责人',
			dataIndex: 'headName',
			key: 'headName',
			width: 100,
			customFilterDropdown: true,
			onFilter: (value, record) => record.headName.toString().toLowerCase().includes(value.toLowerCase()),
			onFilterDropdownOpenChange: (visible) => {
				if (visible) {
					setTimeout(() => {
						searchInput.value.focus()
					}, 100)
				}
			}
		},
		{
			title: '数量',
			dataIndex: 'totalNumber',
			width: 100,
			key: 'totalNumber',
			sorter: {
				compare: (a, b) => a.totalNumber - b.totalNumber,
				multiple: 1
			}
		},
		{
			title: '对账金额',
			dataIndex: 'reconciliationAmount',
			width: 100,
			key: 'reconciliationAmount',
			sorter: {
				compare: (a, b) => a.totalNumber - b.totalNumber,
				multiple: 1
			}
		},

		{
			title: '操作',
			key: 'operation'
		}
	]
	const rowExpandableFilter = (record) => {
		return record.dataSource.length > 0
	}
	const handleSearch = (selectedKeys, confirm, dataIndex) => {
		confirm()
		state.searchText = selectedKeys[0]
		state.searchedColumn = dataIndex
	}
	const handleReset = (clearFilters) => {
		clearFilters({
			confirm: true
		})
		state.searchText = ''
	}
	const innerColumns = [
		{
			title: '创建人',
			dataIndex: 'createUserName',
			key: 'createUserName'
		},
		{
			title: '加密狗',
			dataIndex: 'contentText',
			key: 'contentText'
		},

		{
			title: '原序列码',
			dataIndex: 'oldCode',
			key: 'oldCode'
		},

		{
			title: '软件出货名称',
			dataIndex: 'alias',
			key: 'alias'
		},

		{
			title: '软件简称',
			dataIndex: 'abbreviation',
			key: 'abbreviation'
		},

		{
			title: '版本类型',
			dataIndex: 'versionType',
			key: 'versionType'
		},

		{
			title: '配置硬件',
			dataIndex: 'hardware',
			key: 'hardware'
		},
		{
			title: '备注',
			dataIndex: 'remark',
			key: 'remark'
		},
		{
			title: '版本备注',
			dataIndex: 'versionRemark',
			key: 'versionRemark'
		},
		{
			title: '操作',
			key: 'operation',
			width: '150px',
			align: 'center',
			fixed: 'right'
		}
	]

	const loadingRowTable = async (targetId) => {
		const find = productListData.value.find((v) => {
			return v.id === targetId
		})
		if (!find) {
			return
		}
		find.loading = true

		try {
			find.dataSource = await bizSaleProjectProductInfoApi.bizSaleProjectProductInfoList({
				targetIds: targetId
			})
		} catch (e) {
			console.error(e)
		} finally {
			find.loading = false
		}
	}

	const removeInfo = async (record, target) => {
		const find = productListData.value.find((v) => {
			return v.id === record.id
		})
		if (!find) {
			return
		}

		find.loading = true

		try {
			await bizSaleProjectProductInfoApi.bizSaleProjectProductInfoDelete([{ id: target.id }])
		} catch (e) {
			console.error(e)
		} finally {
			find.loading = false
		}

		await loadingRowTable(record.id)
	}

	const handleEdit = (record) => {
	   console.log(record);
       formRef.value.onOpen(record); // 传递当前行的数据
	};


	const openAddForm = (record) => {
		if (record.dataSource.length) {
			let i = record.dataSource.length - 1
			let copy = cloneDeep(record.dataSource[i])
			copy.id = ''

			formRef.value.onOpen(copy)
			return
		}
		formRef.value.onOpen({
			targetId: record.id,
			productId: record.productId
		})
	}

	//项目详情
	const detailRef = useTemplateRef('detailRef')
	const openDetail = (record) => {
		detailRef.value.onOpen({ id: record.projectId })
	}

	//导出相关

	const { load: exportData, loading: exportLoading } = useLoading(async () => {
		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')
		// 定义列标题
		const column = [
			'序列',
			'对账金额',
			'业务员',
			'项目名称',
			'产品名称',
			'软件通用名称',
			'版本类型',
			'配置硬件',
			'软件简称',
			'备注',
			'版本备注',
			'原序列码',
			'加密狗'
		]
		let arr = ['alias','versionType', 'hardware', 'abbreviation', 'remark', 'versionRemark', 'oldCode', 'contentText' ]
		let maxLength = column.map((v) => {
			return v.length
		})
		let start = 1
		// 添加标题行
		worksheet.addRow(column)
		productListData.value.forEach((v) => {
			for (let i = 0; i < v.totalNumber; i++) {
				let obj = v.dataSource[i] ? v.dataSource[i] : {}
				let base = [start, v.reconciliationAmount, v.headName, v.projectName, v.productName]
				arr.forEach((key) => {
					if (obj[key]) {
						base.push(obj[key])
					} else {
						base.push('')
					}
				})

				base.forEach((v, index) => {
					if (v.length > maxLength[index]) {
						maxLength[index] = v.length
					}
				})
				worksheet.addRow(base)

				start++
			}
		})
		// 设置标题行样式
		worksheet.getRow(1).eachCell((cell) => {
			cell.font = { bold: true } // 加粗
			cell.alignment = { horizontal: 'center', vertical: 'middle' } // 居中
		})

		worksheet.addRow(['共计：', ''])
		worksheet.getCell(`B${start + 1}`).value = { formula: `=SUM(B${2}:B${start})` }
		worksheet.getCell(`B${start + 1}`).font = { bold: true }

		// 设置数据行样式
		worksheet.eachRow((row, rowNumber) => {
			if (rowNumber > 1) {
				// 跳过标题行
				row.eachCell((cell) => {
					cell.alignment = { horizontal: 'center', vertical: 'middle' } // 居中
				})
			}
		})

		// 设置列宽
		worksheet.columns = column.map((col, index) => ({
			header: col,
			key: col,
			width: maxLength[index] + 20 // 动态列宽
		}))

		// 设置行高
		worksheet.eachRow((row) => {
			row.height = 20 // 固定行高
		})

		// 生成 Excel 文件
		const buffer = await workbook.xlsx.writeBuffer()
		const file = new Blob([buffer], { type: 'application/octet-stream' })
		saveAs(file, '软件打包总计.xlsx')
	})

	watchEffect(() => {
		const { startCreateTime, endCreateTime } = dateOptions.value[activeDateIndex.value]
		const orgId = activeOrgId.value

		loadResult({
			startCreateTime,
			endCreateTime,
			orgId
		})
	})
</script>

<style scoped></style>
