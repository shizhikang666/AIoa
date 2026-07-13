import { useLoading } from '@/composables/useLoading'
import { exportWordDocx } from '@/utils/exportUtil/exportDom'
import tool from '@/utils/tool'

export function useUser() {
	const exportUserWord = async (param) => {
		let zzjyxl = ''
		let zzjyzy = ''
		let qrzxl = ''
		let qrzzy = ''

		if (param.onJobEducationJson) {
			const array = JSON.parse(param.onJobEducationJson)
			if (array.length > 0) {
				let index = array.length - 1
				const item = array[index]
				zzjyxl = item.name
				zzjyzy = item.school
			}
		}

		if (param.fullTimeEducationJson) {
			const array = JSON.parse(param.fullTimeEducationJson)
			if (array.length > 0) {
				let index = array.length - 1
				const item = array[index]
				qrzxl = item.name
				qrzzy = item.school
			}
		}

		let familyArray = []
		if (param.familyMembersAndSocialRelationshipsJson) {
			familyArray = JSON.parse(param.familyMembersAndSocialRelationshipsJson)
		}

		const keyObj = {}
		for (let i = 0; i < 6; i++) {
			keyObj['jt_title' + i] = ''
			keyObj['jt_name' + i] = ''
			keyObj['jt_age' + i] = ''
			keyObj['jt_politicalStatus' + i] = ''
			keyObj['jt_unit' + i] = ''

			if (familyArray[i]) {
				keyObj['jt_title' + i] = familyArray[i].title
				keyObj['jt_name' + i] = familyArray[i].name
				keyObj['jt_age' + i] = familyArray[i].age
				keyObj['jt_politicalStatus' + i] = familyArray[i].politicalStatus
				keyObj['jt_unit' + i] = familyArray[i].unit
			}
		}
		const gzbm = tool.translateTree('WorkDepartmentAttributes', param.departmentAttribute)
		const qk = tool.translateTree('PersonalSituation', param.personalInformation)

		let gzbmsx = `${gzbm === '军队' ? '☑' : '□'}军队  ${gzbm === '公安' ? '☑' : '□'}公安  ${
			gzbm === '国家安全部门' ? '☑' : '□'
		}国家安全部门  ${gzbm === '外交系统' ? '☑' : '□'}外交系统  ${
			gzbm === '中国共产党籍贯（包括党报、党刊）' ? '☑' : '□'
		}中国共产党机关（包括党报、党刊）

${gzbm === '其他' ? '☑' : '□'}其他`

		let grqk = `${qk === '中共党员' ? '☑' : '□'}中共党员  ${qk === '共青团员' ? '☑' : '□'}共青团员  ${
			qk === '工人' ? '☑' : '□'
		}工人  ${qk === '农民' ? '☑' : '□'}农民  ${qk === '全日制在校学生' ? '☑' : '□'}全日制在校学生
${qk === '台湾同胞、香港同胞、澳门同胞、海外侨胞' ? '☑' : '□'}台湾同胞、香港同胞、澳门同胞、海外侨胞 ${
			qk === '宗教教职人员' ? '☑' : '□'
		}宗教教职人员  ${qk === '已获得外国国籍人员' ? '☑' : '□'}已获得外国国籍人员  ${qk === '其他' ? '☑' : '□'}其他`

		let data = Object.assign({ ...param, zzjyxl, zzjyzy, qrzxl, qrzzy, gzbmsx, grqk }, keyObj)

		await exportWordDocx('/docxTemplate/人才摸底表.docx', data, {
			filename: `${data.name}人才摸底表.docx`
		})
	}

	return {
		exportUserWord
	}
}
