import React, { useEffect, useState, useCallback } from 'react';
import { Head, usePage, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Button,
    Card,
    CardBody,
    CardHeader,
    Input,
    Select,
    SelectItem,
    Textarea,
    Tabs,
    Tab,
    Spinner,
} from '@heroui/react';
import {
    ArrowLeftIcon,
    SaveIcon,
    EyeIcon,
} from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import BlockPublishingManager from '@/Components/CMS/BlockPublishingManager.jsx';
import { showToast } from '@/utils/toastUtils.jsx';
import axios from 'axios';

const BlockEditor = ({ block = null, locales = [] }) => {
    const { auth } = usePage().props;
    const isEditMode = !!block?.id;

    // Theme radius helper
    const getThemeRadius = () => {
        if (typeof window === 'undefined') return 'lg';
        const rootStyles = getComputedStyle(document.documentElement);
        const borderRadius = rootStyles.getPropertyValue('--borderRadius')?.trim() || '12px';
        const radiusValue = parseInt(borderRadius);
        if (radiusValue === 0) return 'none';
        if (radiusValue <= 4) return 'sm';
        if (radiusValue <= 8) return 'md';
        if (radiusValue <= 16) return 'lg';
        return 'full';
    };

    // Responsive states
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const checkScreenSize = () => {
            setIsMobile(window.innerWidth < 640);
        };
        checkScreenSize();
        window.addEventListener('resize', checkScreenSize);
        return () => window.removeEventListener('resize', checkScreenSize);
    }, []);

    // Form state
    const [formData, setFormData] = useState({
        title: block?.title || '',
        slug: block?.slug || '',
        short_description: block?.short_description || '',
        locale: block?.locale || locales?.[0] || 'en',
        block_data: block?.block_data || {},
        metadata: block?.metadata || {},
    });

    const [loading, setLoading] = useState(false);
    const [publishingModal, setPublishingModal] = useState(false);

    // Handle input change
    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value,
        }));
    };

    // Generate slug from title
    const generateSlug = (title) => {
        return title
            .toLowerCase()
            .trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    };

    // Handle title change with auto-slug
    const handleTitleChange = (e) => {
        const title = e.target.value;
        setFormData(prev => ({
            ...prev,
            title,
            slug: generateSlug(title),
        }));
    };

    // Submit form
    const handleSubmit = async () => {
        if (!formData.title.trim()) {
            showToast.error('Title is required');
            return;
        }

        if (!formData.slug.trim()) {
            showToast.error('Slug is required');
            return;
        }

        setLoading(true);

        const promise = new Promise(async (resolve, reject) => {
            try {
                const url = isEditMode
                    ? route('api.blocks.update', block.id)
                    : route('api.blocks.store');

                const method = isEditMode ? 'post' : 'post';

                const response = await axios({
                    method,
                    url,
                    data: {
                        ...formData,
                        _method: isEditMode ? 'PUT' : undefined,
                    },
                });

                if (response.status === 200 || response.status === 201) {
                    if (!isEditMode) {
                        // Redirect to edit page for new block
                        setTimeout(() => {
                            window.location.href = route('cms.blocks.edit', response.data.data.id);
                        }, 1500);
                    }
                    resolve([response.data.message || (isEditMode ? 'Block updated' : 'Block created')]);
                }
            } catch (error) {
                reject(
                    error.response?.data?.message ||
                    error.response?.data?.errors?.join(', ') ||
                    'Failed to save block'
                );
            } finally {
                setLoading(false);
            }
        });

        showToast.promise(promise, {
            loading: isEditMode ? 'Updating block...' : 'Creating block...',
            success: (data) => data,
            error: (data) => data,
        });
    };

    return (
        <>
            <Head title={isEditMode ? `Edit: ${block.title}` : 'Create Block'} />

            {/* Publishing Manager Modal */}
            {isEditMode && (
                <BlockPublishingManager
                    block={block}
                    isOpen={publishingModal}
                    onClose={() => setPublishingModal(false)}
                />
            )}

            {/* Main Content */}
            <div className="flex flex-col w-full h-full p-4" role="main" aria-label="Block Editor">
                <motion.div
                    initial={{ scale: 0.9, opacity: 0 }}
                    animate={{ scale: 1, opacity: 1 }}
                    transition={{ duration: 0.5 }}
                    className="w-full"
                >
                    <Card
                        className="transition-all duration-200"
                        style={{
                            border: `var(--borderWidth, 2px) solid transparent`,
                            borderRadius: `var(--borderRadius, 12px)`,
                            fontFamily: `var(--fontFamily, "Inter")`,
                            background: `linear-gradient(135deg, 
                                var(--theme-content1, #FAFAFA) 20%, 
                                var(--theme-content2, #F4F4F5) 10%, 
                                var(--theme-content3, #F1F3F4) 20%)`,
                        }}
                    >
                        {/* Card Header */}
                        <CardHeader
                            className="border-b p-0"
                            style={{
                                borderColor: `var(--theme-divider, #E4E4E7)`,
                                background: `linear-gradient(135deg, 
                                    color-mix(in srgb, var(--theme-content1) 50%, transparent) 20%, 
                                    color-mix(in srgb, var(--theme-content2) 30%, transparent) 10%)`,
                            }}
                        >
                            <div className={`${!isMobile ? 'p-6' : 'p-4'} w-full`}>
                                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                    {/* Title Section */}
                                    <div className="flex items-center gap-3 lg:gap-4">
                                        <Button
                                            isIconOnly
                                            variant="light"
                                            onClick={() => window.history.back()}
                                        >
                                            <ArrowLeftIcon className="w-5 h-5" />
                                        </Button>
                                        <div>
                                            <h4 className={`${!isMobile ? 'text-2xl' : 'text-xl'} font-bold`}>
                                                {isEditMode ? 'Edit Block' : 'Create Block'}
                                            </h4>
                                            <p className={`${!isMobile ? 'text-sm' : 'text-xs'} text-default-500`}>
                                                {isEditMode
                                                    ? `Editing "${block.title}"`
                                                    : 'Create a new content block'}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="flex gap-2 flex-wrap">
                                        {isEditMode && (
                                            <Button
                                                color="secondary"
                                                variant="flat"
                                                startContent={<EyeIcon className="w-4 h-4" />}
                                                onClick={() => setPublishingModal(true)}
                                                size={isMobile ? 'sm' : 'md'}
                                            >
                                                Publishing
                                            </Button>
                                        )}

                                        <Button
                                            color="primary"
                                            variant="shadow"
                                            startContent={loading ? <Spinner size="sm" color="current" /> : <SaveIcon className="w-4 h-4" />}
                                            onClick={handleSubmit}
                                            disabled={loading}
                                            size={isMobile ? 'sm' : 'md'}
                                        >
                                            {loading ? 'Saving...' : isEditMode ? 'Update' : 'Create'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardHeader>

                        {/* Card Body */}
                        <CardBody className="p-6">
                            <Tabs aria-label="Block editor tabs" color="primary" variant="light">
                                {/* Basic Info Tab */}
                                <Tab key="basic" title="Basic Information">
                                    <div className="space-y-4 mt-4">
                                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                            <Input
                                                label="Title"
                                                placeholder="Enter block title"
                                                name="title"
                                                value={formData.title}
                                                onChange={handleTitleChange}
                                                onBlur={(e) => {
                                                    if (!formData.slug) {
                                                        setFormData(prev => ({
                                                            ...prev,
                                                            slug: generateSlug(formData.title),
                                                        }));
                                                    }
                                                }}
                                                isRequired
                                                radius={getThemeRadius()}
                                            />

                                            <Input
                                                label="Slug"
                                                placeholder="block-slug"
                                                name="slug"
                                                value={formData.slug}
                                                onChange={handleChange}
                                                description="Auto-generated from title"
                                                isRequired
                                                radius={getThemeRadius()}
                                            />
                                        </div>

                                        <Textarea
                                            label="Short Description"
                                            placeholder="Brief description of this block"
                                            name="short_description"
                                            value={formData.short_description}
                                            onChange={handleChange}
                                            maxRows={3}
                                            radius={getThemeRadius()}
                                        />

                                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                            <Select
                                                label="Locale"
                                                selectedKeys={[formData.locale]}
                                                onSelectionChange={(keys) => {
                                                    setFormData(prev => ({
                                                        ...prev,
                                                        locale: Array.from(keys)[0],
                                                    }));
                                                }}
                                                isRequired
                                                radius={getThemeRadius()}
                                            >
                                                {locales?.map(locale => (
                                                    <SelectItem key={locale}>
                                                        {locale.toUpperCase()}
                                                    </SelectItem>
                                                ))}
                                            </Select>
                                        </div>
                                    </div>
                                </Tab>

                                {/* Content Tab */}
                                <Tab key="content" title="Content">
                                    <div className="space-y-4 mt-4">
                                        <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                            <p className="text-sm text-blue-800 dark:text-blue-300">
                                                <strong>Note:</strong> Advanced content editing will be implemented in a future update. For now, use the block builder for full block management.
                                            </p>
                                        </div>

                                        <Textarea
                                            label="Content (JSON Preview)"
                                            placeholder="Block content structure (JSON)"
                                            value={JSON.stringify(formData.block_data, null, 2)}
                                            isReadOnly
                                            minRows={10}
                                            className="font-mono text-xs"
                                            radius={getThemeRadius()}
                                        />
                                    </div>
                                </Tab>

                                {/* Metadata Tab */}
                                <Tab key="metadata" title="Metadata">
                                    <div className="space-y-4 mt-4">
                                        <Textarea
                                            label="Custom Metadata (JSON)"
                                            placeholder='{ "key": "value" }'
                                            value={JSON.stringify(formData.metadata, null, 2)}
                                            onChange={(e) => {
                                                try {
                                                    const metadata = JSON.parse(e.target.value);
                                                    setFormData(prev => ({
                                                        ...prev,
                                                        metadata,
                                                    }));
                                                } catch (err) {
                                                    // Invalid JSON, allow user to keep typing
                                                }
                                            }}
                                            minRows={8}
                                            className="font-mono text-xs"
                                            radius={getThemeRadius()}
                                        />
                                    </div>
                                </Tab>
                            </Tabs>
                        </CardBody>
                    </Card>
                </motion.div>
            </div>
        </>
    );
};

BlockEditor.layout = (page) => <App children={page} />;
export default BlockEditor;
