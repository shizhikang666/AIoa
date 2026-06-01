import { computed } from 'vue'
import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
import userCenterApi from '@/api/sys/userCenterApi'
import { useProduct } from '@/composables/useProduct'
import { exportWordDocx } from '@/utils/exportUtil/exportDom'
import tool from '@/utils/tool'
import bizOrgApi from '@/api/biz/bizOrgApi'
/**
 * 在树形结构数据中查找指定orgId相关的公司信息
 * @param {Array} treeData - 树形结构数据数组
 * @param {string} orgId - 要查找的组织ID
 * @param {string} categoryFilter - 要筛选的类别，默认为'COMPANY'
 * @returns {Array} - 符合条件的公司信息数组
 */
function findRelatedCompanies(treeData, orgId, categoryFilter = 'COMPANY') {
	if (!Array.isArray(treeData) || !orgId) {
		return []
	}

	// 深度优先搜索，查找目标节点
	function findNodeAndPath(nodes, targetId, path = []) {
		for (const node of nodes) {
			const currentPath = [...path, node]

			// 如果找到目标节点，返回包含该节点的路径
			if (node.id === targetId) {
				return currentPath
			}

			// 如果有子节点，继续递归搜索
			if (node.children && node.children.length > 0) {
				const childResult = findNodeAndPath(node.children, targetId, currentPath)
				if (childResult) {
					return childResult
				}
			}
		}
		return null
	}

	// 查找包含目标节点的路径
	const path = findNodeAndPath(treeData, orgId)

	if (!path) {
		return [] // 未找到目标节点
	}

	// 从路径中筛选出符合条件的节点
	return path.filter((node) => node.category === categoryFilter)
}

/**
 * 查找指定orgId所属的公司信息（从自身向上查找）
 * @param {Array} treeData - 树形结构数据数组
 * @param {string} orgId - 要查找的组织ID
 * @returns {Object|null} - 找到的公司节点，如果没找到返回null
 */
function findCompanyByOrgId(treeData, orgId) {
	const companies = findRelatedCompanies(treeData, orgId, 'COMPANY')

	// 返回第一个找到的公司（从自身开始向上查找，所以第一个就是最近的）
	return companies.length > 0 ? companies[0] : null
}

/**
 * 查找指定orgId的所有相关公司信息（包括自身和所有父级公司）
 * @param {Array} treeData - 树形结构数据数组
 * @param {string} orgId - 要查找的组织ID
 * @returns {Array} - 所有相关的公司信息数组
 */
function findAllRelatedCompanies(treeData, orgId) {
	return findRelatedCompanies(treeData, orgId, 'COMPANY')
}
const exportProjectInitInvoice = async (projectId) => {
	const result = await bizSaleProjectApi.bizSaleProjectDetail({ id: projectId })

	const projectBaseInfo = result.bizSaleProject || {}
	const orgTree = await bizOrgApi.orgTree()
	const company = findCompanyByOrgId(orgTree, projectBaseInfo.org)

	let companyName = company ? company.name : ''
	const projectProductItemList = result?.productItems
	let specsObject = {}
	tool.dictListByPath('PRODUCT_DICT', 'PRODUCT_SPECS').filter((item) => {
		specsObject[item.value] = item.label
	})
	const tdStyle = `style="border: 1px solid #000;  height:50px; text-align: center;"`
	let htmlTemplate = `
		<table style="width: 570px;border: 1px solid #000; text-align: center; border-collapse: collapse" border="1px;margin:0 auto;">
			<tr>
			<td ${tdStyle}>产品名称</td>
			<td ${tdStyle}>产品构成</td>
			<td ${tdStyle}>数量</td>
			<td ${tdStyle}>单位</td>
			<td ${tdStyle}>备注</td>
			</tr>
	`
	const render = (str, v, zIndex = 0) => {
		let specs = specsObject[v.productSpecs || v.specs]

		let length = v.children ? v.children.length : ''
		let oneRowObject = v.children ? v.children.shift() : v
		oneRowObject.remark = v.remark
		let rowspan =
			zIndex === 0
				? `
					<td ${tdStyle} rowSpan="${length}">${v.productName}(${v.number}套)</td>
				`
				: ''
		let result = `
			<tr >
				${rowspan}
			<td ${tdStyle}>${oneRowObject.productName}</td>
			<td ${tdStyle}>${oneRowObject.number}</td>
			<td ${tdStyle}>${specs}</td>
			<td ${tdStyle}>${oneRowObject.remark ? oneRowObject.remark : ''}</td>
			</tr>
		`

		if (v.children) {
			v.children.forEach((item) => {
				item.remark = v.remark
				result += render(str, item, zIndex + 1)
			})
		}

		return result
	}
	projectProductItemList.forEach((v) => {
		let str = render(htmlTemplate, v)
		htmlTemplate += str
	})
	htmlTemplate += '</table>'
	projectBaseInfo.freightCategory = tool.dictTypeDataByPath('FREIGHT_CATEGORY', projectBaseInfo.freightCategory)
	projectBaseInfo.logisticsCategory = projectBaseInfo.logisticsCategory
		? tool.dictTypeDataByPath('LOGISTICS_CATEGORY', projectBaseInfo.logisticsCategory)
		: '无'
	const data = {
		companyName,
		...projectBaseInfo,
		nickName: projectBaseInfo.headName,
		completionDate: projectBaseInfo.completionDate,
		projectId,
		headName: projectBaseInfo.headName,
		headPhone: projectBaseInfo.headPhone,
		projectProductItemList,
		html: htmlTemplate
	}

	await exportWordDocx('/docxTemplate/销售项目发货单-1.docx', data, {
		filename: projectBaseInfo.projectName + '.docx'
	})
}

export function useProject(projectInfo) {
	const isDeal = computed(() => {
		let record = projectInfo.value
		return (
			record.projectState !== 'FOLLOW' &&
			record.projectState !== 'DISCARD' &&
			record.projectState !== 'PENDING_APPROVAL'
		)
	})

	return {
		exportProjectInitInvoice,
		isDeal
	}
}
