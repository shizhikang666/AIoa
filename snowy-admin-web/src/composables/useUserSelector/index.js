import userApi from "@/api/biz/bizUserApi";
import userCenterApi from "@/api/sys/userCenterApi";

export  function  useUserSelector() {
	const selectorApiFunction = {
		orgTreeApi: (param) => {
			return userApi.userOrgTreeSelector(param).then((data) => {
				return Promise.resolve(data)
			})
		},
		userPageApi: (param) => {
			return userApi.userSelector(param).then((data) => {
				return Promise.resolve(data)
			})
		},
		checkedUserListApi: (param) => {
			return userCenterApi.userCenterGetUserListByIdList(param).then((data) => {
				return Promise.resolve(data)
			})
		}
	}
	return  selectorApiFunction

}
