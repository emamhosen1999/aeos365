import React, { useCallback, useState, useEffect, useMemo } from 'react';
import {
    Modal,
    ModalContent,
    ModalHeader,
    ModalBody,
    ModalFooter,
    Button,
    Card,
    CardBody,
    CardHeader,
    Input,
    Select,
    SelectItem,
    Chip,
    Checkbox,
    Divider,
    Tab,
    Tabs,
    Spinner,
} from '@heroui/react';
import {
    ArrowUpTrayIcon,
    CalendarIcon,
    ArchiveBoxIcon,
    SparklesIcon,
    EyeIcon,
    EyeSlashIcon,
    CheckCircleIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import { showToast } from '@/utils/toastUtils';
import axios from 'axios';

const BlockPublishingManager = ({ block, isOpen, onClose, onPublished }) => {
    const [loading, setLoading] = useState(false);
    const [currentPublish, setCurrentPublish] = useState(null);
    const [activeTab, setActiveTab] = useState('status');

    // Form state
    const [publishForm, setPublishForm] = useState({
        visibility: 'public',
        is_featured: false,
        version_summary: '',
        version_description: '',
        notes: '',
    });

    const [scheduleForm, setScheduleForm] = useState({
        scheduled_publish_at: '',
        visibility: 'public',
        is_featured: false,
        require_approval: false,
        version_summary: '',
        version_description: '',
        notes: '',
    });

    const [archiveForm, setArchiveForm] = useState({
        reason: '',
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

    // Fetch current publishing status
    const fetchPublishingStatus = useCallback(async () => {
        if (!block?.id) return;

        try {
            const response = await axios.get(
                route('api.blocks.publishing.show', block.id)
            );
            if (response.data?.data) {
                setCurrentPublish(response.data.data);
            }
        } catch (error) {
            console.error('Failed to fetch publishing status:', error);
        }
    }, [block?.id]);

    useEffect(() => {
        if (isOpen) {
            fetchPublishingStatus();
        }
    }, [isOpen, fetchPublishingStatus]);

    // Handle immediate publish
    const handlePublish = async () => {
        setLoading(true);
        const promise = new Promise(async (resolve, reject) => {
            try {
                const response = await axios.post(
                    route('api.blocks.publishing.publish', block.id),
                    publishForm
                );
                if (response.status === 200) {
                    setCurrentPublish(response.data.data);
                    setPublishForm({
                        visibility: 'public',
                        is_featured: false,
                        version_summary: '',
                        version_description: '',
                        notes: '',
                    });
                    onPublished?.();
                    await fetchPublishingStatus();
                    resolve(['Block published successfully']);
                }
            } catch (error) {
                reject(error.response?.data?.message || 'Failed to publish block');
            } finally {
                setLoading(false);
            }
        });

        showToast.promise(promise, {
            loading: 'Publishing block...',
            success: 'Block published successfully',
            error: (data) => data,
        });
    };

    // Handle schedule publish
    const handleSchedule = async () => {
        if (!scheduleForm.scheduled_publish_at) {
            showToast.error('Please select a publish date and time');
            return;
        }

        setLoading(true);
        const promise = new Promise(async (resolve, reject) => {
            try {
                const response = await axios.post(
                    route('api.blocks.publishing.schedule', block.id),
                    scheduleForm
                );
                if (response.status === 200) {
                    setCurrentPublish(response.data.data);
                    setScheduleForm({
                        scheduled_publish_at: '',
                        visibility: 'public',
                        is_featured: false,
                        require_approval: false,
                        version_summary: '',
                        version_description: '',
                        notes: '',
                    });
                    setActiveTab('status');
                    onPublished?.();
                    resolve(['Block scheduled successfully']);
                }
            } catch (error) {
                reject(error.response?.data?.message || 'Failed to schedule block');
            } finally {
                setLoading(false);
            }
        });

        showToast.promise(promise, {
            loading: 'Scheduling block...',
            success: 'Block scheduled successfully',
            error: (data) => data,
        });
    };

    // Handle archive
    const handleArchive = async () => {
        setLoading(true);
        const promise = new Promise(async (resolve, reject) => {
            try {
                const response = await axios.post(
                    route('api.blocks.publishing.archive', block.id),
                    archiveForm
                );
                if (response.status === 200) {
                    setCurrentPublish(response.data.data);
                    setArchiveForm({ reason: '' });
                    setActiveTab('status');
                    onPublished?.();
                    resolve(['Block archived successfully']);
                }
            } catch (error) {
                reject(error.response?.data?.message || 'Failed to archive block');
            } finally {
                setLoading(false);
            }
        });

        showToast.promise(promise, {
            loading: 'Archiving block...',
            success: 'Block archived successfully',
            error: (data) => data,
        });
    };

    // Handle restore
    const handleRestore = async () => {
        setLoading(true);
        const promise = new Promise(async (resolve, reject) => {
            try {
                const response = await axios.post(
                    route('api.blocks.publishing.restore', block.id),
                    { visibility: 'public' }
                );
                if (response.status === 200) {
                    setCurrentPublish(response.data.data);
                    setActiveTab('status');
                    onPublished?.();
                    resolve(['Block restored successfully']);
                }
            } catch (error) {
                reject(error.response?.data?.message || 'Failed to restore block');
            } finally {
                setLoading(false);
            }
        });

        showToast.promise(promise, {
            loading: 'Restoring block...',
            success: 'Block restored successfully',
            error: (data) => data,
        });
    };

    // Status badge rendering
    const renderStatusBadge = () => {
        if (!currentPublish) return null;

        const statusMap = {
            published: { color: 'success', icon: CheckCircleIcon },
            scheduled: { color: 'warning', icon: ClockIcon },
            draft: { color: 'default', icon: null },
            archived: { color: 'danger', icon: ArchiveBoxIcon },
        };

        const status = statusMap[currentPublish.status] || statusMap.draft;
        const Icon = status.icon;

        return (
            <div className="flex items-center gap-2">
                <Chip
                    color={status.color}
                    variant="flat"
                    startContent={Icon ? <Icon className="w-4 h-4" /> : undefined}
                    className="capitalize"
                >
                    {currentPublish.status}
                </Chip>
                {currentPublish.visibility && (
                    <Chip
                        color="secondary"
                        variant="flat"
                        startContent={currentPublish.visibility === 'private' ? <EyeSlashIcon className="w-4 h-4" /> : <EyeIcon className="w-4 h-4" />}
                        className="capitalize"
                    >
                        {currentPublish.visibility}
                    </Chip>
                )}
            </div>
        );
    };

    // Publish immediately tab
    const publishTab = (
        <div className="space-y-4">
            <div>
                <label className="block text-sm font-medium mb-2">Visibility</label>
                <Select
                    value={publishForm.visibility}
                    onChange={(e) => setPublishForm({ ...publishForm, visibility: e.target.value })}
                    radius={getThemeRadius()}
                    className="max-w-xs"
                >
                    <SelectItem key="public">Public</SelectItem>
                    <SelectItem key="internal">Internal</SelectItem>
                    <SelectItem key="private">Private</SelectItem>
                    <SelectItem key="draft_only">Draft Only</SelectItem>
                </Select>
            </div>

            <Checkbox
                isSelected={publishForm.is_featured}
                onChange={(e) => setPublishForm({ ...publishForm, is_featured: e.target.checked })}
                className="mt-3"
            >
                <span className="flex items-center gap-2">
                    <SparklesIcon className="w-4 h-4" />
                    Feature this block
                </span>
            </Checkbox>

            <Input
                label="Version Summary"
                placeholder="Brief description of changes"
                value={publishForm.version_summary}
                onValueChange={(value) => setPublishForm({ ...publishForm, version_summary: value })}
                maxLength={255}
                radius={getThemeRadius()}
            />

            <Input
                label="Version Description"
                placeholder="Detailed description (optional)"
                value={publishForm.version_description}
                onValueChange={(value) => setPublishForm({ ...publishForm, version_description: value })}
                maxLength={1000}
                radius={getThemeRadius()}
                isMultiline
                minRows={3}
            />

            <Input
                label="Notes"
                placeholder="Internal notes (optional)"
                value={publishForm.notes}
                onValueChange={(value) => setPublishForm({ ...publishForm, notes: value })}
                maxLength={500}
                radius={getThemeRadius()}
                isMultiline
                minRows={2}
            />

            <Button
                color="success"
                onClick={handlePublish}
                disabled={loading}
                className="w-full"
                startContent={loading ? <Spinner size="sm" color="current" /> : <ArrowUpTrayIcon className="w-4 h-4" />}
            >
                {loading ? 'Publishing...' : 'Publish Now'}
            </Button>
        </div>
    );

    // Schedule publish tab
    const scheduleTab = (
        <div className="space-y-4">
            <Input
                label="Publish Date & Time"
                type="datetime-local"
                value={scheduleForm.scheduled_publish_at}
                onChange={(e) => setScheduleForm({ ...scheduleForm, scheduled_publish_at: e.target.value })}
                radius={getThemeRadius()}
                isRequired
                description="Schedule this block to be published at a future date/time"
            />

            <div>
                <label className="block text-sm font-medium mb-2">Visibility</label>
                <Select
                    value={scheduleForm.visibility}
                    onChange={(e) => setScheduleForm({ ...scheduleForm, visibility: e.target.value })}
                    radius={getThemeRadius()}
                    className="max-w-xs"
                >
                    <SelectItem key="public">Public</SelectItem>
                    <SelectItem key="internal">Internal</SelectItem>
                    <SelectItem key="private">Private</SelectItem>
                    <SelectItem key="draft_only">Draft Only</SelectItem>
                </Select>
            </div>

            <Checkbox
                isSelected={scheduleForm.is_featured}
                onChange={(e) => setScheduleForm({ ...scheduleForm, is_featured: e.target.checked })}
            >
                <span className="flex items-center gap-2">
                    <SparklesIcon className="w-4 h-4" />
                    Feature this block
                </span>
            </Checkbox>

            <Checkbox
                isSelected={scheduleForm.require_approval}
                onChange={(e) => setScheduleForm({ ...scheduleForm, require_approval: e.target.checked })}
            >
                Require approval before publishing
            </Checkbox>

            <Input
                label="Version Summary"
                placeholder="Brief description of changes"
                value={scheduleForm.version_summary}
                onValueChange={(value) => setScheduleForm({ ...scheduleForm, version_summary: value })}
                maxLength={255}
                radius={getThemeRadius()}
            />

            <Input
                label="Version Description"
                placeholder="Detailed description (optional)"
                value={scheduleForm.version_description}
                onValueChange={(value) => setScheduleForm({ ...scheduleForm, version_description: value })}
                maxLength={1000}
                radius={getThemeRadius()}
                isMultiline
                minRows={3}
            />

            <Input
                label="Notes"
                placeholder="Internal notes (optional)"
                value={scheduleForm.notes}
                onValueChange={(value) => setScheduleForm({ ...scheduleForm, notes: value })}
                maxLength={500}
                radius={getThemeRadius()}
                isMultiline
                minRows={2}
            />

            <Button
                color="warning"
                onClick={handleSchedule}
                disabled={loading || !scheduleForm.scheduled_publish_at}
                className="w-full"
                startContent={loading ? <Spinner size="sm" color="current" /> : <CalendarIcon className="w-4 h-4" />}
            >
                {loading ? 'Scheduling...' : 'Schedule Publication'}
            </Button>
        </div>
    );

    // Archive tab
    const archiveTab = (
        <div className="space-y-4">
            <div className="bg-danger/10 border border-danger/30 rounded-lg p-4">
                <p className="text-sm font-medium text-danger">Archive this block</p>
                <p className="text-xs text-default-600 mt-1">
                    Archiving will unpublish this block and make it invisible to all users. It can be restored later.
                </p>
            </div>

            <Input
                label="Reason for archiving"
                placeholder="Why are you archiving this block? (optional)"
                value={archiveForm.reason}
                onValueChange={(value) => setArchiveForm({ reason: value })}
                maxLength={500}
                radius={getThemeRadius()}
                isMultiline
                minRows={3}
            />

            <Button
                color="danger"
                onClick={handleArchive}
                disabled={loading}
                className="w-full"
                startContent={loading ? <Spinner size="sm" color="current" /> : <ArchiveBoxIcon className="w-4 h-4" />}
            >
                {loading ? 'Archiving...' : 'Archive Block'}
            </Button>
        </div>
    );

    // Status tab
    const statusTab = (
        <div className="space-y-4">
            {currentPublish ? (
                <>
                    <div className="space-y-3">
                        <div>
                            <p className="text-xs text-default-500 uppercase tracking-wide">Status</p>
                            {renderStatusBadge()}
                        </div>

                        {currentPublish.published_at && (
                            <div>
                                <p className="text-xs text-default-500 uppercase tracking-wide">Published Date</p>
                                <p className="text-sm font-medium">
                                    {new Date(currentPublish.published_at).toLocaleString()}
                                </p>
                            </div>
                        )}

                        {currentPublish.scheduled_publish_at && (
                            <div>
                                <p className="text-xs text-default-500 uppercase tracking-wide">Scheduled For</p>
                                <p className="text-sm font-medium">
                                    {new Date(currentPublish.scheduled_publish_at).toLocaleString()}
                                </p>
                            </div>
                        )}

                        {currentPublish.archived_at && (
                            <div>
                                <p className="text-xs text-default-500 uppercase tracking-wide">Archived Date</p>
                                <p className="text-sm font-medium">
                                    {new Date(currentPublish.archived_at).toLocaleString()}
                                </p>
                            </div>
                        )}

                        <Divider />

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <p className="text-xs text-default-500">Views</p>
                                <p className="text-lg font-semibold">{currentPublish.view_count || 0}</p>
                            </div>
                            <div>
                                <p className="text-xs text-default-500">Interactions</p>
                                <p className="text-lg font-semibold">{currentPublish.interaction_count || 0}</p>
                            </div>
                        </div>
                    </div>

                    {currentPublish.status === 'archived' && (
                        <Button
                            color="primary"
                            onClick={handleRestore}
                            disabled={loading}
                            className="w-full"
                        >
                            {loading ? 'Restoring...' : 'Restore Block'}
                        </Button>
                    )}
                </>
            ) : (
                <div className="text-center py-8">
                    <Spinner size="lg" />
                    <p className="text-sm text-default-500 mt-4">Loading publishing status...</p>
                </div>
            )}
        </div>
    );

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            size="2xl"
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
                    <h2 className="text-lg font-semibold">Publishing Manager</h2>
                    <p className="text-xs text-default-500">Manage block publication and scheduling</p>
                </ModalHeader>

                <ModalBody>
                    <Tabs
                        aria-label="Publishing options"
                        color="primary"
                        variant="light"
                        value={activeTab}
                        onValueChange={setActiveTab}
                    >
                        <Tab key="status" title="Status" content={statusTab} />
                        <Tab key="publish" title="Publish Now" content={publishTab} />
                        <Tab key="schedule" title="Schedule" content={scheduleTab} />
                        <Tab key="archive" title="Archive" content={archiveTab} />
                    </Tabs>
                </ModalBody>

                <ModalFooter>
                    <Button variant="flat" onPress={onClose} disabled={loading}>
                        Close
                    </Button>
                </ModalFooter>
            </ModalContent>
        </Modal>
    );
};

export default BlockPublishingManager;
