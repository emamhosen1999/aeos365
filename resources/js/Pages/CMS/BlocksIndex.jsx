import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Head, usePage, Link, router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Button,
    Card,
    CardBody,
    CardHeader,
    Input,
    Select,
    SelectItem,
    Table,
    TableHeader,
    TableColumn,
    TableBody,
    TableRow,
    TableCell,
    Chip,
    Pagination,
    Dropdown,
    DropdownTrigger,
    DropdownMenu,
    DropdownItem,
    Modal,
    ModalContent,
    ModalHeader,
    ModalBody,
    ModalFooter,
    Skeleton,
    User,
} from '@heroui/react';
import {
    PlusIcon,
    MagnifyingGlassIcon,
    EllipsisVerticalIcon,
    PencilIcon,
    TrashIcon,
    EyeIcon,
    CheckCircleIcon,
    ClockIcon,
    ArchiveBoxIcon,
} from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import StatsCards from '@/Components/StatsCards.jsx';
import BlockPublishingManager from '@/Components/CMS/BlockPublishingManager.jsx';
import RevisionHistory from '@/Components/CMS/RevisionHistory.jsx';
import axios from 'axios';
import { showToast } from '@/utils/toastUtils.jsx';

const CmsBlocksIndex = ({ blocks: initialBlocks = [], stats = {}, locales = [] }) => {
    const { auth } = usePage().props;

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
    const [isTablet, setIsTablet] = useState(false);

    useEffect(() => {
        const checkScreenSize = () => {
            setIsMobile(window.innerWidth < 640);
            setIsTablet(window.innerWidth < 768);
        };
        checkScreenSize();
        window.addEventListener('resize', checkScreenSize);
        return () => window.removeEventListener('resize', checkScreenSize);
    }, []);

    // State management
    const [loading, setLoading] = useState(false);
    const [blocks, setBlocks] = useState(initialBlocks);
    const [filters, setFilters] = useState({
        search: '',
        locale: '',
        status: '',
    });
    const [pagination, setPagination] = useState({
        currentPage: 1,
        perPage: 25,
        total: initialBlocks.length,
    });
    const [statsData, setStatsData] = useState(stats);

    // Modal states
    const [publishingModal, setPublishingModal] = useState({ isOpen: false, block: null });
    const [revisionModal, setRevisionModal] = useState({ isOpen: false, block: null });
    const [deleteModal, setDeleteModal] = useState({ isOpen: false, block: null, loading: false });

    // Permission checks
    const canCreate = auth.permissions?.includes('cms.blocks.create') || false;
    const canEdit = auth.permissions?.includes('cms.blocks.update') || false;
    const canDelete = auth.permissions?.includes('cms.blocks.delete') || false;
    const canPublish = auth.permissions?.includes('cms.blocks.publishing.publish') || false;

    // Fetch blocks
    const fetchBlocks = useCallback(async () => {
        setLoading(true);
        try {
            const response = await axios.get(route('api.blocks.index'), {
                params: {
                    page: pagination.currentPage,
                    per_page: pagination.perPage,
                    search: filters.search || undefined,
                    locale: filters.locale || undefined,
                    status: filters.status || undefined,
                },
            });

            if (response.data?.data) {
                setBlocks(response.data.data);
                setPagination(prev => ({
                    ...prev,
                    total: response.data.meta?.total || 0,
                }));
            }
        } catch (error) {
            showToast.error('Failed to load blocks');
            console.error(error);
        } finally {
            setLoading(false);
        }
    }, [pagination.currentPage, pagination.perPage, filters]);

    useEffect(() => {
        fetchBlocks();
    }, [fetchBlocks]);

    // Delete block
    const handleDelete = async () => {
        if (!deleteModal.block?.id) return;

        setDeleteModal(prev => ({ ...prev, loading: true }));

        const promise = new Promise(async (resolve, reject) => {
            try {
                const response = await axios.delete(
                    route('api.blocks.destroy', deleteModal.block.id)
                );
                if (response.status === 200) {
                    setBlocks(blocks.filter(b => b.id !== deleteModal.block.id));
                    setDeleteModal({ isOpen: false, block: null, loading: false });
                    resolve(['Block deleted successfully']);
                }
            } catch (error) {
                reject(error.response?.data?.message || 'Failed to delete block');
                setDeleteModal(prev => ({ ...prev, loading: false }));
            }
        });

        showToast.promise(promise, {
            loading: 'Deleting block...',
            success: 'Block deleted successfully',
            error: (data) => data,
        });
    };

    // Stats cards data
    const statsCards = useMemo(() => [
        {
            title: 'Total Blocks',
            value: statsData.total || 0,
            icon: <DocumentIcon className="w-6 h-6" />,
            color: 'text-primary',
            iconBg: 'bg-primary/20',
        },
        {
            title: 'Published',
            value: statsData.published || 0,
            icon: <CheckCircleIcon className="w-6 h-6" />,
            color: 'text-success',
            iconBg: 'bg-success/20',
        },
        {
            title: 'Scheduled',
            value: statsData.scheduled || 0,
            icon: <ClockIcon className="w-6 h-6" />,
            color: 'text-warning',
            iconBg: 'bg-warning/20',
        },
        {
            title: 'Drafts',
            value: statsData.drafts || 0,
            icon: <PencilIcon className="w-6 h-6" />,
            color: 'text-secondary',
            iconBg: 'bg-secondary/20',
        },
    ], [statsData]);

    // Status badge rendering
    const renderStatusBadge = (block) => {
        const publish = block.current_publish;
        if (!publish) return <Chip color="default" size="sm">No Status</Chip>;

        const statusMap = {
            published: { color: 'success', icon: CheckCircleIcon },
            scheduled: { color: 'warning', icon: ClockIcon },
            draft: { color: 'default', icon: null },
            archived: { color: 'danger', icon: ArchiveBoxIcon },
        };

        const status = statusMap[publish.status] || statusMap.draft;
        const Icon = status.icon;

        return (
            <Chip
                color={status.color}
                variant="flat"
                size="sm"
                startContent={Icon ? <Icon className="w-3 h-3" /> : undefined}
                className="capitalize"
            >
                {publish.status}
            </Chip>
        );
    };

    const columns = [
        { key: 'title', label: 'Title', width: '25%' },
        { key: 'slug', label: 'Slug', width: '20%' },
        { key: 'locale', label: 'Locale', width: '12%' },
        { key: 'status', label: 'Status', width: '15%' },
        { key: 'updated_at', label: 'Updated', width: '15%' },
        { key: 'actions', label: 'Actions', width: '13%' },
    ];

    // Format date
    const formatDate = (date) => {
        if (!date) return '-';
        return new Date(date).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    // Render cell
    const renderCell = (block, columnKey) => {
        switch (columnKey) {
            case 'title':
                return (
                    <div>
                        <p className="font-medium text-sm">{block.title}</p>
                        <p className="text-xs text-default-500">{block.short_description}</p>
                    </div>
                );
            case 'slug':
                return <span className="text-xs font-mono text-default-600">{block.slug}</span>;
            case 'locale':
                return <Chip size="sm" variant="flat">{block.locale?.toUpperCase()}</Chip>;
            case 'status':
                return renderStatusBadge(block);
            case 'updated_at':
                return <span className="text-sm">{formatDate(block.updated_at)}</span>;
            case 'actions':
                return (
                    <div className="flex items-center gap-2">
                        <Dropdown>
                            <DropdownTrigger>
                                <Button isIconOnly size="sm" variant="light">
                                    <EllipsisVerticalIcon className="w-5 h-5" />
                                </Button>
                            </DropdownTrigger>
                            <DropdownMenu aria-label="Block actions">
                                {canEdit && (
                                    <DropdownItem
                                        key="edit"
                                        startContent={<PencilIcon className="w-4 h-4" />}
                                        href={route('cms.blocks.edit', block.id)}
                                    >
                                        Edit
                                    </DropdownItem>
                                )}

                                {canPublish && (
                                    <>
                                        <DropdownItem
                                            key="publish"
                                            startContent={<EyeIcon className="w-4 h-4" />}
                                            onPress={() => setPublishingModal({ isOpen: true, block })}
                                        >
                                            Publishing
                                        </DropdownItem>
                                        <DropdownItem
                                            key="revisions"
                                            startContent={<CheckCircleIcon className="w-4 h-4" />}
                                            onPress={() => setRevisionModal({ isOpen: true, block })}
                                        >
                                            Revisions
                                        </DropdownItem>
                                    </>
                                )}

                                {canDelete && (
                                    <DropdownItem
                                        key="delete"
                                        className="text-danger"
                                        color="danger"
                                        startContent={<TrashIcon className="w-4 h-4" />}
                                        onPress={() => setDeleteModal({ isOpen: true, block, loading: false })}
                                    >
                                        Delete
                                    </DropdownItem>
                                )}
                            </DropdownMenu>
                        </Dropdown>
                    </div>
                );
            default:
                return block[columnKey] || '-';
        }
    };

    return (
        <>
            <Head title="CMS Blocks" />

            {/* Publishing Modal */}
            {publishingModal.block && (
                <BlockPublishingManager
                    block={publishingModal.block}
                    isOpen={publishingModal.isOpen}
                    onClose={() => setPublishingModal({ isOpen: false, block: null })}
                    onPublished={() => fetchBlocks()}
                />
            )}

            {/* Revisions Modal */}
            {revisionModal.block && (
                <RevisionHistory
                    block={revisionModal.block}
                    isOpen={revisionModal.isOpen}
                    onClose={() => setRevisionModal({ isOpen: false, block: null })}
                />
            )}

            {/* Delete Confirmation Modal */}
            <Modal
                isOpen={deleteModal.isOpen}
                onClose={() => setDeleteModal({ isOpen: false, block: null, loading: false })}
                size="md"
                classNames={{
                    base: 'bg-content1',
                    header: 'border-b border-divider',
                    body: 'py-6',
                    footer: 'border-t border-divider',
                }}
            >
                <ModalContent>
                    <ModalHeader>Delete Block</ModalHeader>
                    <ModalBody>
                        <p className="text-sm">
                            Are you sure you want to delete <strong>{deleteModal.block?.title}</strong>? This action cannot be undone.
                        </p>
                    </ModalBody>
                    <ModalFooter>
                        <Button
                            variant="flat"
                            onPress={() => setDeleteModal({ isOpen: false, block: null, loading: false })}
                            disabled={deleteModal.loading}
                        >
                            Cancel
                        </Button>
                        <Button
                            color="danger"
                            onPress={handleDelete}
                            disabled={deleteModal.loading}
                            isLoading={deleteModal.loading}
                        >
                            Delete
                        </Button>
                    </ModalFooter>
                </ModalContent>
            </Modal>

            {/* Main Content */}
            <div className="flex flex-col w-full h-full p-4" role="main" aria-label="CMS Blocks Management">
                <div className="space-y-4">
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
                                fontFamily: `var(--fontFamily, "Inter")`,
                                transform: `scale(var(--scale, 1))`,
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
                                            <div
                                                className={`${!isMobile ? 'p-3' : 'p-2'} rounded-xl`}
                                                style={{
                                                    background: `color-mix(in srgb, var(--theme-primary) 15%, transparent)`,
                                                    borderRadius: `var(--borderRadius, 12px)`,
                                                }}
                                            >
                                                <EyeIcon
                                                    className={`${!isMobile ? 'w-8 h-8' : 'w-6 h-6'}`}
                                                    style={{ color: 'var(--theme-primary)' }}
                                                />
                                            </div>
                                            <div>
                                                <h4 className={`${!isMobile ? 'text-2xl' : 'text-xl'} font-bold`}>
                                                    CMS Blocks
                                                </h4>
                                                <p className={`${!isMobile ? 'text-sm' : 'text-xs'} text-default-500`}>
                                                    Manage content blocks and publishing workflow
                                                </p>
                                            </div>
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="flex gap-2 flex-wrap">
                                            {canCreate && (
                                                <Button
                                                    color="primary"
                                                    variant="shadow"
                                                    startContent={<PlusIcon className="w-4 h-4" />}
                                                    as={Link}
                                                    href={route('cms.blocks.create')}
                                                    size={isMobile ? 'sm' : 'md'}
                                                >
                                                    Create Block
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>

                            {/* Card Body */}
                            <CardBody className="p-6">
                                {/* Stats Cards */}
                                <StatsCards stats={statsCards} className="mb-6" />

                                {/* Filter Section */}
                                <div className="flex flex-col sm:flex-row gap-4 mb-6">
                                    <Input
                                        label="Search blocks"
                                        placeholder="Search by title..."
                                        value={filters.search}
                                        onValueChange={(value) => {
                                            setFilters({ ...filters, search: value });
                                            setPagination(prev => ({ ...prev, currentPage: 1 }));
                                        }}
                                        startContent={<MagnifyingGlassIcon className="w-4 h-4 text-default-400" />}
                                        radius={getThemeRadius()}
                                        className="flex-1"
                                        size="sm"
                                    />

                                    <Select
                                        label="Locale"
                                        placeholder="All Locales"
                                        selectedKeys={filters.locale ? [filters.locale] : []}
                                        onSelectionChange={(keys) => {
                                            setFilters({ ...filters, locale: Array.from(keys)[0] || '' });
                                            setPagination(prev => ({ ...prev, currentPage: 1 }));
                                        }}
                                        className="w-full sm:w-40"
                                        size="sm"
                                    >
                                        {locales?.map(locale => (
                                            <SelectItem key={locale}>{locale.toUpperCase()}</SelectItem>
                                        ))}
                                    </Select>

                                    <Select
                                        label="Status"
                                        placeholder="All Status"
                                        selectedKeys={filters.status ? [filters.status] : []}
                                        onSelectionChange={(keys) => {
                                            setFilters({ ...filters, status: Array.from(keys)[0] || '' });
                                            setPagination(prev => ({ ...prev, currentPage: 1 }));
                                        }}
                                        className="w-full sm:w-40"
                                        size="sm"
                                    >
                                        <SelectItem key="published">Published</SelectItem>
                                        <SelectItem key="scheduled">Scheduled</SelectItem>
                                        <SelectItem key="draft">Draft</SelectItem>
                                        <SelectItem key="archived">Archived</SelectItem>
                                    </Select>
                                </div>

                                {/* Blocks Table */}
                                {loading ? (
                                    <div className="space-y-3">
                                        {Array.from({ length: 5 }).map((_, i) => (
                                            <Skeleton key={i} className="h-16 rounded-lg" />
                                        ))}
                                    </div>
                                ) : (
                                    <Table
                                        aria-label="CMS blocks table"
                                        isHeaderSticky
                                        classNames={{
                                            wrapper: 'shadow-none border border-divider rounded-lg',
                                            th: 'bg-default-100 text-default-600 font-semibold',
                                            td: 'py-3',
                                        }}
                                    >
                                        <TableHeader columns={columns}>
                                            {(column) => (
                                                <TableColumn key={column.key} width={column.width}>
                                                    {column.label}
                                                </TableColumn>
                                            )}
                                        </TableHeader>
                                        <TableBody
                                            items={blocks}
                                            emptyContent="No blocks found"
                                        >
                                            {(block) => (
                                                <TableRow key={block.id}>
                                                    {(columnKey) => (
                                                        <TableCell>
                                                            {renderCell(block, columnKey)}
                                                        </TableCell>
                                                    )}
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                )}

                                {/* Pagination */}
                                {pagination.total > pagination.perPage && (
                                    <div className="flex justify-center mt-6">
                                        <Pagination
                                            total={Math.ceil(pagination.total / pagination.perPage)}
                                            page={pagination.currentPage}
                                            onChange={(page) => {
                                                setPagination(prev => ({ ...prev, currentPage: page }));
                                                window.scrollTo(0, 0);
                                            }}
                                            color="primary"
                                        />
                                    </div>
                                )}
                            </CardBody>
                        </Card>
                    </motion.div>
                </div>
            </div>
        </>
    );
};

CmsBlocksIndex.layout = (page) => <App children={page} />;
export default CmsBlocksIndex;
