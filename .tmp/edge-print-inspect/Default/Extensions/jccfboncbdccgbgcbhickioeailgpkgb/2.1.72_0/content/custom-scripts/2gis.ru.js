"use strict";

jsonPruneXhrResponse('result.banners', '', 'propsToMatch', '/ads/advise');
jsonPruneXhrResponse('banners', '', 'propsToMatch', '/advise/gta');
jsonPruneXhrResponse('result.items.[].ads', '', 'propsToMatch', '/markers/clustered?');
jsonPruneXhrResponse('result.items.[].ads result.ad.gta.banners', '', 'propsToMatch', 'items?');
jsonPruneXhrResponse('result.items.[].ads', '', 'propsToMatch', 'items/byid?');
jsonPruneXhrResponse('result.ad', '', 'propsToMatch', 'items/byid?');
jsonPruneXhrResponse('result.pinned_items.[-].offer result.items.[-].offer', '', 'propsToMatch', 'product/items_by_branch?');
pruneJson('data.searchContext.{}.ads', 'data.searchContext');
pruneJson('data.gta.{}.banners', 'data.gta');
pruneJson('data.gta.{}.banners', 'initialState');