import { ref } from 'vue'
import bizUserApi from '@/api/biz/bizUserApi'
import tool from '@/utils/tool'

export function useOrg() {
	const treeData = ref([])
	const treeDefaultExpandedKeys = ref([])
	const loadingTreeData = async () => {
		const res = await bizUserApi.userOrgTreeSelector()
		if (res !== null) {
			treeData.value = res
			// 默认展开2级
			treeData.value.forEach((item) => {
				// 因为0的顶级
				if (item.parentId === '0') {
					treeDefaultExpandedKeys.value.push(item.id)
					// 取到下级ID
					if (item.children) {
						item.children.forEach((items) => {
							treeDefaultExpandedKeys.value.push(items.id)
						})
					}
				}
			})
		}
	}

	/**
	 * 在树形结构中根据部门ID查找其所属公司
	 * @param {Array} treeData - 树形结构数据
	 * @param {string} deptId - 要查找的部门ID
	 * @returns {Object|null} - 返回找到的公司对象，如果没有找到返回null
	 */
	const findCompanyByDeptId = (treeData, deptId) => {
		for (const node of treeData) {
			// 如果当前节点是公司且包含children
			if (node.category === 'COMPANY') {
				if (deptId === node.id) {
					return node
				}
				// 在当前公司的直接子部门中查找
				if (node.children && node.children.length > 0) {
					// 检查直接子部门
					const foundInDirect = node.children.some((child) => child.id === deptId)
					if (foundInDirect) {
						return node
					}

					// 递归查找嵌套的子部门（如果需要）
					for (const child of node.children) {
						if (child.category === 'DEPT' && child.children) {
							const foundInNested = findInNestedDepts(child, deptId)
							if (foundInNested) {
								return node
							}
						}
					}
				}
			} else if (node.category === 'DEPT' && node.children) {
				// 如果当前节点是部门且有子部门，继续递归
				const result = findCompanyByDeptId(node.children, deptId)
				if (result) return result
			}
		}
		return null
	}

	/**
	 * 在嵌套的部门结构中查找部门ID
	 * @param {Object} dept - 部门节点
	 * @param {string} deptId - 要查找的部门ID
	 * @returns {boolean} - 是否找到
	 */
	const findInNestedDepts = (dept, deptId) => {
		if (dept.id === deptId) {
			return true
		}

		if (dept.children && dept.children.length > 0) {
			for (const child of dept.children) {
				if (findInNestedDepts(child, deptId)) {
					return true
				}
			}
		}

		return false
	}

	/**
	 * 获取部门树中的第一个部门ID
	 * @param {Array} depts - 部门列表
	 * @returns {string|null} - 第一个部门ID
	 */
	const getFirstDeptId = (depts) => {
		if (!depts || depts.length === 0) return null

		const firstDept = depts[0]

		// 如果第一个节点是部门且有ID，返回ID
		if (firstDept.id) {
			return firstDept.id
		}

		// 如果第一个节点有子部门，递归获取第一个子部门
		if (firstDept.children && firstDept.children.length > 0) {
			return getFirstDeptId(firstDept.children)
		}

		return null
	}

	/**
	 * 主函数：根据部门ID找到仓库部门ID
	 * @param {Array} treeData - 树形结构数据
	 * @param {Array} warehouses
	 * @param {string} deptId - 部门ID
	 * @returns {string|null} - 仓库部门ID或公司第一个部门ID
	 */
	const findWarehouseDeptId = (treeData, warehouses, deptId) => {
		// 1. 找到部门所属的公司
		const company = findCompanyByDeptId(treeData, deptId)

		if (!company) {
			console.error('未找到部门所属的公司')
			return null
		}

		const find = warehouses.find((v) => {
			return v.org === company.id
		})

		return find
	}

	/**
	 * 递归查找树形结构中节点的路径
	 * @param {Array} nodes - 树形结构节点
	 * @param {string} targetId - 目标节点ID
	 * @param {Array} path - 当前路径（递归使用）
	 * @returns {Array|null} - 目标节点路径或null
	 */
	const findNodePathById = (nodes, targetId, path = []) => {
		if (!Array.isArray(nodes) || !targetId) {
			return null
		}
		for (const node of nodes) {
			const currentPath = [...path, node]
			if (node.id === targetId) {
				return currentPath
			}
			if (node.children && node.children.length > 0) {
				const result = findNodePathById(node.children, targetId, currentPath)
				if (result) {
					return result
				}
			}
		}
		return null
	}

	/**
	 * 获取最高公司ID
	 * @param {Array} orgTreeData - 组织树数据
	 * @param {string} orgId - 目标组织ID
	 * @returns {Object|null} - 最高公司对象或null
	 */
	const findTopCompanyByOrgId = (orgTreeData, orgId) => {
		const path = findNodePathById(orgTreeData, orgId)
		if (!path) {
			return null
		}
		return path.find((node) => node.category === 'COMPANY') || path[0] || null
	}

	/**
	 * 获取当前用户所属的最高公司ID
	 * @param {Array} orgTreeData - 组织树数据
	 * @returns {string|null} - 最高公司ID或null
	 */
	const getCurrentUserTopOrgId = (orgTreeData = treeData.value) => {
		const userInfo = tool.data.get('USER_INFO')
		const currentOrgId = userInfo?.orgId
		if (!currentOrgId) {
			return null
		}
		const topCompany = findTopCompanyByOrgId(orgTreeData, currentOrgId)
		return topCompany?.id || null
	}

	const treeFieldNames = { children: 'children', title: 'name', key: 'id' }
	return {
		treeData,
		treeDefaultExpandedKeys,
		loadingTreeData,
		treeFieldNames,
		findWarehouseDeptId,
		findCompanyByDeptId,
		findTopCompanyByOrgId,
		getCurrentUserTopOrgId
	}
}
