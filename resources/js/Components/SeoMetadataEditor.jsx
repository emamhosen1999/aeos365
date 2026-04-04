import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Input,
	Modal,
	ModalBody,
	ModalContent,
	ModalFooter,
	ModalHeader,
	Select,
	SelectItem,
	Chip,
	Progress,
	Divider,
	Textarea,
	Tab,
	Tabs
} from "@heroui/react";
import {
	DocumentIcon,
	MagnifyingGlassIcon,
	CheckCircleIcon,
	ExclamationIcon,
	InformationCircleIcon,
	TrashIcon,
	PlusIcon
} from "@heroicons/react/24/outline";
import axios from 'axios';
import { showToast } from '@/utils/toastUtils';

const SeoMetadataEditor = ({ blockId, pageId, locale = 'en', onSave }) => {
	const [isMobile, setIsMobile] = useState(false);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [metadata, setMetadata] = useState(null);
	const [formData, setFormData] = useState({
		meta_title: '',
		meta_description: '',
		meta_keywords: '',
		og_title: '',
		og_description: '',
		og_image: '',
		og_type: 'article',
		twitter_card: 'summary_large_image',
		twitter_title: '',
		twitter_description: '',
		twitter_image: '',
		twitter_creator: '@aeroenterprise',
		canonical_url: '',
		robots_index: 'index',
		robots_follow: 'follow',
		schema_json: '{}',
		schema_type: 'Article',
	});
	const [keywords, setKeywords] = useState([]);
	const [newKeyword, setNewKeyword] = useState('');
	const [audit, setAudit] = useState(null);

	const getThemeRadius = () => {
		if (typeof window === 'undefined') return 'lg';
		const rootStyles = getComputedStyle(document.documentElement);
		const borderRadius = rootStyles.getPropertyValue('--borderRadius')?.trim() || '12px';
		const radiusValue = parseInt(borderRadius);
		if (radiusValue === 0) return 'none';
		if (radiusValue <= 4) return 'sm';
		if (radiusValue <= 8) return 'md';
		if (radiusValue <= 12) return 'lg';
		return 'xl';
	};

	useEffect(() => {
		const checkScreenSize = () => {
			setIsMobile(window.innerWidth < 640);
		};
		checkScreenSize();
		window.addEventListener('resize', checkScreenSize);
		return () => window.removeEventListener('resize', checkScreenSize);
	}, []);

	const fetchMetadata = useCallback(async () => {
		setLoading(true);
		try {
			const response = await axios.get(
				route('seo-metadata.show', { page: pageId, block: blockId, locale })
			);
			if (response.status === 200 && response.data.data) {
				const data = response.data.data;
				setMetadata(data);
				setFormData({
					meta_title: data.meta_title || '',
					meta_description: data.meta_description || '',
					meta_keywords: data.meta_keywords || '',
					og_title: data.og_title || '',
					og_description: data.og_description || '',
					og_image: data.og_image || '',
					og_type: data.og_type || 'article',
					twitter_card: data.twitter_card || 'summary_large_image',
					twitter_title: data.twitter_title || '',
					twitter_description: data.twitter_description || '',
					twitter_image: data.twitter_image || '',
					twitter_creator: data.twitter_creator || '@aeroenterprise',
					canonical_url: data.canonical_url || '',
					robots_index: data.robots_index || 'index',
					robots_follow: data.robots_follow || 'follow',
					schema_json: data.schema_json ? JSON.stringify(data.schema_json, null, 2) : '{}',
					schema_type: data.schema_type || 'Article',
				});
				setKeywords(data.keywords || []);
			}
		} catch (error) {
			if (error.response?.status !== 404) {
				showToast.promise(Promise.reject(error), { error: 'Failed to fetch SEO metadata' });
			}
		} finally {
			setLoading(false);
		}
	}, [blockId, pageId, locale]);

	const fetchAudit = useCallback(async () => {
		try {
			const response = await axios.get(
				route('seo-audit', { page: pageId, block: blockId, locale })
			);
			if (response.status === 200) {
				setAudit(response.data);
			}
		} catch (error) {
			console.error('Audit fetch error:', error);
		}
	}, [blockId, pageId, locale]);

	useEffect(() => {
		fetchMetadata();
	}, [fetchMetadata]);

	const handleFieldChange = (field, value) => {
		setFormData(prev => ({ ...prev, [field]: value }));
	};

	const handleSave = async () => {
		setSaving(true);

		const promise = new Promise(async (resolve, reject) => {
			try {
				const endpoint = metadata
					? route('seo-metadata.update', { page: pageId, block: blockId, locale })
					: route('seo-metadata.store', { page: pageId, block: blockId });

				const method = metadata ? 'put' : 'post';

				const response = await axios({
					method,
					url: endpoint,
					data: {
						...formData,
						locale: locale,
						schema_json: formData.schema_json ? JSON.parse(formData.schema_json) : {}
					}
				});

				if (response.status === 200 || response.status === 201) {
					setMetadata(response.data.data);
					setKeywords(response.data.data.keywords || []);
					resolve([response.data.message || 'SEO metadata saved successfully']);
					await fetchAudit();
					if (onSave) onSave(response.data.data);
				}
			} catch (error) {
				reject(error.response?.data?.errors || ['Failed to save']);
			} finally {
				setSaving(false);
			}
		});

		showToast.promise(promise, {
			loading: 'Saving SEO metadata...',
			success: (data) => data.join(', '),
			error: (err) => Array.isArray(err) ? err.join(', ') : err
		});
	};

	const handleAddKeyword = async () => {
		if (!newKeyword.trim()) return;

		try {
			const response = await axios.post(
				route('seo-keywords.store', { page: pageId, block: blockId, metadata: metadata.id }),
				{ keyword: newKeyword, keyword_type: 'secondary' }
			);

			if (response.status === 201) {
				setKeywords(prev => [...prev, response.data.data]);
				setNewKeyword('');
				showToast.promise(Promise.resolve(['Keyword added']), {
					success: (msg) => msg[0]
				});
			}
		} catch (error) {
			showToast.promise(Promise.reject(error), { error: 'Failed to add keyword' });
		}
	};

	const handleRemoveKeyword = async (keywordId) => {
		try {
			await axios.delete(
				route('seo-keywords.destroy', { keyword: keywordId })
			);
			setKeywords(prev => prev.filter(k => k.id !== keywordId));
			showToast.promise(Promise.resolve(['Keyword removed']), {
				success: (msg) => msg[0]
			});
		} catch (error) {
			showToast.promise(Promise.reject(error), { error: 'Failed to remove keyword' });
		}
	};

	const getScoreColor = (score) => {
		if (score >= 80) return 'success';
		if (score >= 60) return 'warning';
		return 'danger';
	};

	const getIssueIcon = (level) => {
		switch (level) {
			case 'error':
				return <ExclamationIcon className="w-4 h-4" />;
			case 'warning':
				return <ExclamationIcon className="w-4 h-4" />;
			case 'info':
				return <InformationCircleIcon className="w-4 h-4" />;
			default:
				return <CheckCircleIcon className="w-4 h-4" />;
		}
	};

	if (loading) {
		return (
			<div className="flex items-center justify-center p-8">
				<div className="text-center">
					<div className="animate-spin mb-4">
						<div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full" />
					</div>
					<p className="text-default-500">Loading SEO metadata...</p>
				</div>
			</div>
		);
	}

	return (
		<>
			<div className="flex flex-col w-full gap-4" role="main">
				<motion.div
					initial={{ scale: 0.9, opacity: 0 }}
					animate={{ scale: 1, opacity: 1 }}
					transition={{ duration: 0.5 }}
				>
					<Card
						className="transition-all duration-200"
						style={{
							border: `var(--borderWidth, 2px) solid transparent`,
							borderRadius: `var(--borderRadius, 12px)`,
							background: `linear-gradient(135deg, 
								var(--theme-content1, #FAFAFA) 20%, 
								var(--theme-content2, #F4F4F5) 10%, 
								var(--theme-content3, #F1F3F4) 20%)`,
						}}
					>
						{/* Header */}
						<CardHeader className="border-b p-0"
							style={{
								borderColor: `var(--theme-divider, #E4E4E7)`
							}}
						>
							<div className={`${!isMobile ? 'p-6' : 'p-4'} w-full`}>
								<div className="flex items-center gap-3 lg:gap-4 mb-4">
									<div className="p-3 rounded-xl"
										style={{
											background: `color-mix(in srgb, var(--theme-primary) 15%, transparent)`,
										}}
									>
										<DocumentIcon className="w-6 h-6" style={{ color: 'var(--theme-primary)' }} />
									</div>
									<div>
										<h2 className={`${!isMobile ? 'text-2xl' : 'text-xl'} font-bold`}>
											SEO Metadata Editor
										</h2>
										<p className="text-sm text-default-500">
											Optimize your content for search engines
										</p>
									</div>
								</div>

								{/* SEO Score Display */}
								{metadata && (
									<div className="bg-default-100 p-4 rounded-lg">
										<div className="flex items-center justify-between mb-2">
											<span className="text-sm font-semibold">SEO Score</span>
											<span className={`text-lg font-bold text-${getScoreColor(metadata.seo_score)}`}>
												{metadata.seo_score}/100
											</span>
										</div>
										<Progress
											value={metadata.seo_score}
											color={getScoreColor(metadata.seo_score)}
											className="h-2"
										/>
									</div>
								)}
							</div>
						</CardHeader>

						{/* Body with Tabs */}
						<CardBody className="p-6">
							<Tabs
								aria-label="SEO metadata sections"
								color="primary"
								variant="underlined"
								classNames={{
									tabList: "gap-6 w-full relative rounded-none p-0 border-b border-divider",
									cursor: "w-full bg-primary",
									tab: "max-w-fit px-0 h-12",
									panel: "pt-6 pb-0"
								}}
							>
								{/* Meta Tags Tab */}
								<Tab
									key="meta"
									title={
										<div className="flex items-center space-x-2">
											<span>Meta Tags</span>
										</div>
									}
								>
									<div className="space-y-4">
										<div>
											<Input
												label="Meta Title"
												placeholder="Enter page title (max 60 chars)"
												value={formData.meta_title}
												onValueChange={(value) => handleFieldChange('meta_title', value.substring(0, 60))}
												maxLength={60}
												description={`${formData.meta_title.length}/60 characters`}
												variant="bordered"
												radius={getThemeRadius()}
												classNames={{ inputWrapper: "bg-default-100" }}
											/>
										</div>

										<div>
											<Textarea
												label="Meta Description"
												placeholder="Enter page description (max 160 chars)"
												value={formData.meta_description}
												onValueChange={(value) => handleFieldChange('meta_description', value.substring(0, 160))}
												maxLength={160}
												description={`${formData.meta_description.length}/160 characters`}
												variant="bordered"
												radius={getThemeRadius()}
												classNames={{ inputWrapper: "bg-default-100" }}
												rows={3}
											/>
										</div>

										<div>
											<Input
												label="Meta Keywords"
												placeholder="Comma-separated keywords"
												value={formData.meta_keywords}
												onValueChange={(value) => handleFieldChange('meta_keywords', value)}
												variant="bordered"
												radius={getThemeRadius()}
												classNames={{ inputWrapper: "bg-default-100" }}
											/>
										</div>

										<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
											<Select
												label="Robots Index"
												selectedKeys={[formData.robots_index]}
												onSelectionChange={(keys) => handleFieldChange('robots_index', Array.from(keys)[0])}
												variant="bordered"
												radius={getThemeRadius()}
												classNames={{ trigger: "bg-default-100" }}
											>
												<SelectItem key="index">Index</SelectItem>
												<SelectItem key="noindex">No Index</SelectItem>
											</Select>

											<Select
												label="Robots Follow"
												selectedKeys={[formData.robots_follow]}
												onSelectionChange={(keys) => handleFieldChange('robots_follow', Array.from(keys)[0])}
												variant="bordered"
												radius={getThemeRadius()}
												classNames={{ trigger: "bg-default-100" }}
											>
												<SelectItem key="follow">Follow</SelectItem>
												<SelectItem key="nofollow">No Follow</SelectItem>
											</Select>
										</div>

										<div>
											<Input
												label="Canonical URL"
												placeholder="https://example.com/page"
												value={formData.canonical_url}
												onValueChange={(value) => handleFieldChange('canonical_url', value)}
												variant="bordered"
												radius={getThemeRadius()}
												classNames={{ inputWrapper: "bg-default-100" }}
											/>
										</div>
									</div>
								</Tab>

								{/* Open Graph Tab */}
								<Tab
									key="og"
									title={
										<div className="flex items-center space-x-2">
											<span>Open Graph</span>
										</div>
									}
								>
									<div className="space-y-4">
										<Select
											label="OG Type"
											selectedKeys={[formData.og_type]}
											onSelectionChange={(keys) => handleFieldChange('og_type', Array.from(keys)[0])}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ trigger: "bg-default-100" }}
										>
											<SelectItem key="article">Article</SelectItem>
											<SelectItem key="product">Product</SelectItem>
											<SelectItem key="website">Website</SelectItem>
											<SelectItem key="blog">Blog</SelectItem>
										</Select>

										<Input
											label="OG Title"
											placeholder="Sharing title"
											value={formData.og_title}
											onValueChange={(value) => handleFieldChange('og_title', value)}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ inputWrapper: "bg-default-100" }}
										/>

										<Textarea
											label="OG Description"
											placeholder="Sharing description"
											value={formData.og_description}
											onValueChange={(value) => handleFieldChange('og_description', value)}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ inputWrapper: "bg-default-100" }}
											rows={3}
										/>

										<Input
											label="OG Image URL"
											placeholder="https://example.com/image.jpg"
											value={formData.og_image}
											onValueChange={(value) => handleFieldChange('og_image', value)}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ inputWrapper: "bg-default-100" }}
										/>
									</div>
								</Tab>

								{/* Twitter Card Tab */}
								<Tab
									key="twitter"
									title={
										<div className="flex items-center space-x-2">
											<span>Twitter</span>
										</div>
									}
								>
									<div className="space-y-4">
										<Select
											label="Twitter Card Type"
											selectedKeys={[formData.twitter_card]}
											onSelectionChange={(keys) => handleFieldChange('twitter_card', Array.from(keys)[0])}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ trigger: "bg-default-100" }}
										>
											<SelectItem key="summary">Summary</SelectItem>
											<SelectItem key="summary_large_image">Summary Large Image</SelectItem>
											<SelectItem key="player">Player</SelectItem>
										</Select>

										<Input
											label="Twitter Title"
											placeholder="Tweet title"
											value={formData.twitter_title}
											onValueChange={(value) => handleFieldChange('twitter_title', value)}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ inputWrapper: "bg-default-100" }}
										/>

										<Textarea
											label="Twitter Description"
											placeholder="Tweet description"
											value={formData.twitter_description}
											onValueChange={(value) => handleFieldChange('twitter_description', value)}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ inputWrapper: "bg-default-100" }}
											rows={3}
										/>

										<Input
											label="Twitter Image URL"
											placeholder="https://example.com/twitter.jpg"
											value={formData.twitter_image}
											onValueChange={(value) => handleFieldChange('twitter_image', value)}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ inputWrapper: "bg-default-100" }}
										/>

										<Input
											label="Twitter Creator"
											placeholder="@username"
											value={formData.twitter_creator}
											onValueChange={(value) => handleFieldChange('twitter_creator', value)}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ inputWrapper: "bg-default-100" }}
										/>
									</div>
								</Tab>

								{/* Schema.org Tab */}
								<Tab
									key="schema"
									title={
										<div className="flex items-center space-x-2">
											<span>Schema</span>
										</div>
									}
								>
									<div className="space-y-4">
										<Select
											label="Schema Type"
											selectedKeys={[formData.schema_type]}
											onSelectionChange={(keys) => handleFieldChange('schema_type', Array.from(keys)[0])}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ trigger: "bg-default-100" }}
										>
											<SelectItem key="Article">Article</SelectItem>
											<SelectItem key="BlogPosting">Blog Posting</SelectItem>
											<SelectItem key="Product">Product</SelectItem>
											<SelectItem key="WebPage">Web Page</SelectItem>
											<SelectItem key="Organization">Organization</SelectItem>
										</Select>

										<Textarea
											label="Schema JSON"
											placeholder="{}"
											value={formData.schema_json}
											onValueChange={(value) => handleFieldChange('schema_json', value)}
											variant="bordered"
											radius={getThemeRadius()}
											classNames={{ inputWrapper: "bg-default-100" }}
											rows={8}
											monospaced
										/>
									</div>
								</Tab>

								{/* Keywords Tab */}
								<Tab
									key="keywords"
									title={
										<div className="flex items-center space-x-2">
											<span>Keywords</span>
											{keywords.length > 0 && (
												<Chip size="sm" variant="flat" color="primary">
													{keywords.length}
												</Chip>
											)}
										</div>
									}
								>
									<div className="space-y-4">
										{metadata && (
											<div className="flex gap-2">
												<Input
													placeholder="Add new keyword"
													value={newKeyword}
													onValueChange={setNewKeyword}
													variant="bordered"
													radius={getThemeRadius()}
													classNames={{ inputWrapper: "bg-default-100" }}
												/>
												<Button
													isIconOnly
													color="primary"
													onPress={handleAddKeyword}
													disabled={!newKeyword.trim()}
												>
													<PlusIcon className="w-4 h-4" />
												</Button>
											</div>
										)}

										<div className="space-y-2">
											{keywords.length === 0 ? (
												<p className="text-default-500 text-center py-4">No keywords added yet</p>
											) : (
												keywords.map(keyword => (
													<div
														key={keyword.id}
														className="flex items-center justify-between p-3 bg-default-100 rounded-lg"
													>
														<div>
															<p className="font-medium">{keyword.keyword}</p>
															<p className="text-xs text-default-500">
																Type: {keyword.keyword_type} | Rank: #{keyword.keyword_rank}
															</p>
														</div>
														<Button
															isIconOnly
															variant="light"
															size="sm"
															onPress={() => handleRemoveKeyword(keyword.id)}
														>
															<TrashIcon className="w-4 h-4 text-danger" />
														</Button>
													</div>
												))
											)}
										</div>
									</div>
								</Tab>

								{/* Audit Tab */}
								<Tab
									key="audit"
									title={
										<div className="flex items-center space-x-2">
											<span>Audit</span>
										</div>
									}
								>
									{audit ? (
										<div className="space-y-4">
											<div className="grid grid-cols-1 md:grid-cols-3 gap-4">
												<Card className="bg-default-100">
													<CardBody className="text-center">
														<p className="text-sm text-default-500">Score</p>
														<p className={`text-2xl font-bold text-${getScoreColor(audit.seo_score)}`}>
															{audit.seo_score}
														</p>
													</CardBody>
												</Card>
												<Card className="bg-default-100">
													<CardBody className="text-center">
														<p className="text-sm text-default-500">Views</p>
														<p className="text-2xl font-bold">{audit.view_count}</p>
													</CardBody>
												</Card>
												<Card className="bg-default-100">
													<CardBody className="text-center">
														<p className="text-sm text-default-500">CTR</p>
														<p className="text-2xl font-bold">{(audit.avg_click_through_rate * 100).toFixed(1)}%</p>
													</CardBody>
												</Card>
											</div>

											{audit.issues && audit.issues.length > 0 && (
												<div>
													<h3 className="font-semibold mb-3">Issues & Recommendations</h3>
													<div className="space-y-2">
														{audit.issues.map((issue, idx) => (
															<div key={idx} className="flex gap-3 p-3 bg-default-100 rounded-lg">
																{getIssueIcon(issue.level)}
																<div className="flex-1">
																	<p className="text-sm font-medium capitalize">{issue.level}</p>
																	<p className="text-xs text-default-500">{issue.message}</p>
																</div>
															</div>
														))}
													</div>
												</div>
											)}
										</div>
									) : (
										<p className="text-center text-default-500 py-4">Run audit to see results</p>
									)}
								</Tab>
							</Tabs>

							<Divider className="my-6" />

							{/* Action Buttons */}
							<div className="flex justify-end gap-2">
								<Button
									variant="flat"
									onPress={fetchAudit}
									disabled={saving || !metadata}
								>
									Run Audit
								</Button>
								<Button
									color="primary"
									onPress={handleSave}
									disabled={saving}
									isLoading={saving}
								>
									{saving ? 'Saving...' : 'Save Changes'}
								</Button>
							</div>
						</CardBody>
					</Card>
				</motion.div>
			</div>
		</>
	);
};

export default SeoMetadataEditor;
