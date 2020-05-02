export default {
	getModuleStatus: (state, slug) => state.settings['nv_pro_' + slug + '_status'],
	getOption: (state, slug ) => state.settings[slug],
	getProOption: (state, slug ) => state.settings['nv_pro_' + slug],
	getLicenseTier: (state) => state.tier,
	getToast: (state) => state.toast,
	getTab: (state) => state.currentTab
};