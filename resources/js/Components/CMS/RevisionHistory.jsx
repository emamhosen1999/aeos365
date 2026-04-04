import React, { useEffect, useState, useCallback, useMemo } from 'react';
import {
    Card,
    CardBody,
    CardHeader,
    Table,
    TableHeader,
    TableColumn,
    TableBody,
    TableRow,
    TableCell,
    Chip,
    Button,
    Select,
    SelectItem,
    Pagination,
    Modal,
    ModalContent,
    ModalHeader,
    ModalBody,
    ModalFooter,
    Spinner,
    Input,
    User,
} from '@heroui/react';
import {
    CheckCircleIcon,
    PencilSquareIcon,
    ArrowUpTrayIcon,
    ArchiveBoxIcon,
    ArrowUturnLeftIcon,
    ClockIcon,
    ExclamationCircleIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    EyeIcon,
} from '@heroicons/react/24/outline';
import axios from 'axios';
import { showToast } from '@/utils/toastUtils';

const RevisionHistory = ({ block, isOpen, onClose }) => {
    const [revisions, setRevisions] = useState([]);
    const [loading, setLoading] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [perPage] = useState(10);
    const [filters, setFilters] = useState({
        type: '',
        search: '',
    });
    const [diffModal, setDiffModal] = useState({
        isOpen: false,
        revision: null,
        diff: null,
        loading: false,
    });

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

    // Revision type metadata
    const revisionTypeMap = {
        created: { icon: CheckCircleIcon, color: 'success', label: 'Created' },
        updated: { icon: PencilSquareIcon, color: 'default', label: 'Updated' },
        published: { icon: ArrowUpTrayIcon, color: 'primary', label: 'Published' },
        archived: { icon: ArchiveBoxIcon, color: 'danger', label: 'Archived' },
        restored: { icon: ArrowUturnLeftIcon, color: 'warning', label: 'Restored' },
        reverted: { icon: ArrowUturnLeftIcon, color: 'default', label: 'Reverted' },
        scheduled: { icon: ClockIcon, color: 'secondary', label: 'Scheduled' },
        approved: { icon: CheckCircleIcon, color: 'success', label: 'Approved' },
        rejected: { icon: ExclamationCircleIcon, color: 'danger', label: 'Rejected' },
    };

    // Fetch revisions
    const fetchRevisions = useCallback(async () => {
        if (!block?.id) return;

        setLoading(true);
        try {
            const response = await axios.get(
                route('api.blocks.publishing.revisions', block.id),
                {
                    params: {
                        page: currentPage,
                        per_page: perPage,
                        type: filters.type || undefined,
                        search: filters.search || undefined,
                    },
                }
            );

            if (response.data?.data) {
                setRevisions(response.data.data);
                setTotalPages(response.data.meta?.last_page || 1);
            }
        } catch (error) {
            showToast.error('Failed to load revisions');
            console.error(error);
        } finally {
            setLoading(false);
        }
    }, [block?.id, currentPage, perPage, filters]);

    useEffect(() => {
        if (isOpen) {
            fetchRevisions();
        }
    }, [isOpen, fetchRevisions]);

    // View diff
    const viewDiff = async (revision) => {
        if (!revision.has_diff) {
            showToast.info('No changes tracked for this revision');
            return;
        }

        setDiffModal({ ...diffModal, loading: true, revision });

        try {
            // Parse existing diff if available
            if (revision.diff_json) {
                setDiffModal({
                    isOpen: true,
                    revision,
                    diff: JSON.parse(revision.diff_json),
                    loading: false,
                });
            } else {
                showToast.info('Diff data not available');
                setDiffModal({ ...diffModal, loading: false });
            }
        } catch (error) {
            showToast.error('Failed to load diff');
            console.error(error);
            setDiffModal({ ...diffModal, loading: false });
        }
    };

    // Get revision icon and color
    const getRevisionMeta = (type) => {
        return revisionTypeMap[type] || revisionTypeMap.updated;
    };

    // Format timestamp
    const formatTime = (timestamp) => {
        return new Date(timestamp).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    // Render revision type chip
    const renderTypeChip = (type) => {
        const meta = getRevisionMeta(type);
        const Icon = meta.icon;
        return (
            <Chip
                startContent={<Icon className="w-4 h-4" />}
                color={meta.color}
                variant="flat"
                size="sm"
                className="capitalize"
            >
                {meta.label}
            </Chip>
        );
    };

    // Render diff modal content
    const renderDiffContent = () => {
        if (!diffModal.diff) return null;

        const { added = 0, removed = 0, modified = 0 } = diffModal.diff.summary || {};

        return (
            <div className="space-y-4 max-h-96 overflow-y-auto">
                {/* Summary */}
                <div className="grid grid-cols-3 gap-3">
                    <div className="bg-success/10 rounded-lg p-3 text-center">
                        <p className="text-xs text-default-500">Added</p>
                        <p className="text-lg font-bold text-success">{added}</p>
                    </div>
                    <div className="bg-danger/10 rounded-lg p-3 text-center">
                        <p className="text-xs text-default-500">Removed</p>
                        <p className="text-lg font-bold text-danger">{removed}</p>
                    </div>
                    <div className="bg-warning/10 rounded-lg p-3 text-center">
                        <p className="text-xs text-default-500">Modified</p>
                        <p className="text-lg font-bold text-warning">{modified}</p>
                    </div>
                </div>

                {/* Changes list */}
                <div className="space-y-2">
                    {diffModal.diff.changes?.map((change, idx) => (
                        <div
                            key={idx}
                            className={`p-3 rounded-lg border-l-4 ${
                                change.type === 'added'
                                    ? 'bg-success/5 border-success'
                                    : change.type === 'removed'
                                    ? 'bg-danger/5 border-danger'
                                    : 'bg-warning/5 border-warning'
                            }`}
                        >
                            <p className="text-xs font-mono text-default-600">{change.field}</p>
                            {change.type === 'removed' && (
                                <p className="text-xs text-danger mt-1">
                                    <span className="font-medium">- </span>
                                    {String(change.old_value).substring(0, 100)}
                                </p>
                            )}
                            {change.type === 'added' && (
                                <p className="text-xs text-success mt-1">
                                    <span className="font-medium">+ </span>
                                    {String(change.new_value).substring(0, 100)}
                                </p>
                            )}
                            {change.type === 'modified' && (
                                <div className="text-xs mt-1 space-y-1">
                                    <p className="text-danger">
                                        <span className="font-medium">- </span>
                                        {String(change.old_value).substring(0, 100)}
                                    </p>
                                    <p className="text-success">
                                        <span className="font-medium">+ </span>
                                        {String(change.new_value).substring(0, 100)}
                                    </p>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        );
    };

    const columns = [
        { key: 'type', label: 'Type', width: '15%' },
        { key: 'user', label: 'By', width: '20%' },
        { key: 'created_at', label: 'Date', width: '22%' },
        { key: 'reason', label: 'Reason/Notes', width: '28%' },
        { key: 'actions', label: 'Actions', width: '15%' },
    ];

    return (
        <>
            <Modal
                isOpen={isOpen}
                onClose={onClose}
                size="4xl"
                scrollBehavior="inside"
                classNames={{
                    base: 'bg-content1',
                    header: 'border-b border-divider',
                    body: 'py-6',
                    footer: 'border-t border-divider',
                }}
            >
                <ModalContent>
                    <ModalHeader className="flex flex-col gap-1">
                        <h2 className="text-lg font-semibold">Revision History</h2>
                        <p className="text-xs text-default-500">Complete audit trail of all changes</p>
                    </ModalHeader>

                    <ModalBody>
                        {/* Filters */}
                        <div className="flex flex-col sm:flex-row gap-3 mb-4">
                            <Input
                                placeholder="Search revisions..."
                                value={filters.search}
                                onValueChange={(value) => {
                                    setFilters({ ...filters, search: value });
                                    setCurrentPage(1);
                                }}
                                startContent={<MagnifyingGlassIcon className="w-4 h-4 text-default-400" />}
                                radius={getThemeRadius()}
                                className="flex-1"
                                size="sm"
                            />
                            <Select
                                placeholder="All Types"
                                selectedKeys={filters.type ? [filters.type] : []}
                                onSelectionChange={(keys) => {
                                    setFilters({ ...filters, type: Array.from(keys)[0] || '' });
                                    setCurrentPage(1);
                                }}
                                className="w-full sm:w-48"
                                size="sm"
                            >
                                {Object.entries(revisionTypeMap).map(([key, value]) => (
                                    <SelectItem key={key} className="capitalize">
                                        {value.label}
                                    </SelectItem>
                                ))}
                            </Select>
                        </div>

                        {/* Revisions Table */}
                        {loading ? (
                            <div className="flex justify-center py-8">
                                <Spinner />
                            </div>
                        ) : (
                            <Table
                                aria-label="Revisions table"
                                classNames={{
                                    wrapper: 'shadow-none border border-divider rounded-lg',
                                    th: 'bg-default-100 text-default-600 font-semibold',
                                    td: 'py-3',
                                }}
                            >
                                <TableHeader columns={columns}>
                                    {(column) => (
                                        <TableColumn
                                            key={column.key}
                                            width={column.width}
                                            className="text-xs font-semibold uppercase"
                                        >
                                            {column.label}
                                        </TableColumn>
                                    )}
                                </TableHeader>
                                <TableBody
                                    items={revisions}
                                    emptyContent="No revisions found"
                                >
                                    {(revision) => (
                                        <TableRow key={revision.id}>
                                            <TableCell>
                                                {renderTypeChip(revision.revision_type)}
                                            </TableCell>
                                            <TableCell>
                                                {revision.user_name ? (
                                                    <User
                                                        name={revision.user_name}
                                                        description={revision.user_email}
                                                        avatarProps={{
                                                            size: 'sm',
                                                            name: revision.user_name.charAt(0).toUpperCase(),
                                                        }}
                                                    />
                                                ) : (
                                                    <span className="text-default-500 text-sm">System</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    <span className="text-sm">
                                                        {formatTime(revision.created_at)}
                                                    </span>
                                                    {revision.before_state && (
                                                        <Chip
                                                            size="sm"
                                                            variant="flat"
                                                            startContent={
                                                                <CheckCircleIcon className="w-3 h-3" />
                                                            }
                                                        >
                                                            Has snapshot
                                                        </Chip>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <p className="text-xs text-default-600 line-clamp-2">
                                                    {revision.reason || revision.approval_notes || '-'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {revision.has_diff && (
                                                    <Button
                                                        isIconOnly
                                                        size="sm"
                                                        variant="light"
                                                        onClick={() => viewDiff(revision)}
                                                        title="View changes"
                                                    >
                                                        <EyeIcon className="w-4 h-4" />
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        )}

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="flex justify-center mt-4">
                                <Pagination
                                    total={totalPages}
                                    page={currentPage}
                                    onChange={setCurrentPage}
                                    color="primary"
                                />
                            </div>
                        )}
                    </ModalBody>

                    <ModalFooter>
                        <Button variant="flat" onClick={onClose}>
                            Close
                        </Button>
                    </ModalFooter>
                </ModalContent>
            </Modal>

            {/* Diff Modal */}
            <Modal
                isOpen={diffModal.isOpen}
                onClose={() => setDiffModal({ ...diffModal, isOpen: false })}
                size="3xl"
                scrollBehavior="inside"
                classNames={{
                    base: 'bg-content1',
                    header: 'border-b border-divider',
                    body: 'py-6',
                    footer: 'border-t border-divider',
                }}
            >
                <ModalContent>
                    <ModalHeader className="flex flex-col gap-1">
                        <h2 className="text-lg font-semibold">
                            Changes - {diffModal.revision?.revision_type && renderTypeChip(diffModal.revision.revision_type)}
                        </h2>
                        <p className="text-xs text-default-500">
                            {diffModal.revision && formatTime(diffModal.revision.created_at)}
                        </p>
                    </ModalHeader>

                    <ModalBody>
                        {diffModal.loading ? (
                            <div className="flex justify-center py-8">
                                <Spinner />
                            </div>
                        ) : (
                            renderDiffContent()
                        )}
                    </ModalBody>

                    <ModalFooter>
                        <Button
                            variant="flat"
                            onClick={() => setDiffModal({ ...diffModal, isOpen: false })}
                        >
                            Close
                        </Button>
                    </ModalFooter>
                </ModalContent>
            </Modal>
        </>
    );
};

export default RevisionHistory;
