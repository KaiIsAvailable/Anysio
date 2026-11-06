"use strict";

pruneJson('require.0.3.0.__bbox.require.[].3.1.__bbox.result.data.viewer.sideFeedUnit.nodes.[].new_adverts.nodes.[-].sponsored_data');
pruneJson('require.0.3.0.__bbox.require.[].3.1.__bbox.result.data.serpResponse.results.edges.[-].rendering_strategy.view_model.story.sponsored_data.ad_id');
pruneJson('require.0.3.0.__bbox.require.[].3.1.__bbox.result.data.serpResponse.results.edges.[-].relay_rendering_strategy.view_model.story.sponsored_data.ad_id');
pruneJson('require.0.3.0.__bbox.require.[].3.1.__bbox.result.data.node', 'require.0.3.0.__bbox.require.[].3.1.__bbox.result.data.node.story.sponsored_data.ad_id');
pruneJson('require.0.3.0.__bbox.require.[].3.1.__bbox.result.data.marketplace_search.feed_units.edges.[-].node.story.sponsored_data.ad_id');
pruneJson('require.0.3.0.__bbox.require.[].3.1.__bbox.result.data.viewer.marketplace_feed_stories.edges.[-].node.story.sponsored_data.ad_id');
jsonPruneXhrResponse('data.viewer.sideFeedUnit.nodes.[].new_adverts.nodes.[-].sponsored_data', '', 'propsToMatch', '/graphql');
jsonPruneXhrResponse('data.viewer.instream_video_ads data.scrubber', '', 'propsToMatch', '/api/graphql');
defineConstant('Env.nxghljssj', 'false');
jsonlEditXhrResponse('.data[?.category=="SPONSORED"].node', 'propsToMatch', '/graphql');
jsonlEditXhrResponse('..node[?.*.__typename=="SponsoredData"]', 'propsToMatch', '/graphql');
jsonlEditXhrResponse('..node[?.__typename=="MarketplaceFeedAdStory"]', 'propsToMatch', '/graphql');
jsonlEditXhrResponse('.data.viewer.news_feed.edges.*[?.category=="SPONSORED"].node', 'propsToMatch', '/graphql');
replaceXhrResponseContent('/\\{"node":\\{"role":"SEARCH_ADS"[^\\n]+?cursor":[^}]+\\}/g', '{}', '/api/graphql');
replaceXhrResponseContent('/\\{"node":\\{"__typename":"MarketplaceFeedAdStory"[^\\n]+?"cursor":(?:null|"\\{[^\\n]+?\\}"|[^\\n]+?MarketplaceSearchFeedStoriesEdge")\\}/g', '{}', '/api/graphql');
replaceXhrResponseContent('/\\{"node":\\{"__typename":"VideoHomeFeedUnitSectionComponent"[^\\n]+?"sponsored_data":\\{"ad_id"[^\\n]+?"cursor":null\\}/', '{}', '/api/graphql');