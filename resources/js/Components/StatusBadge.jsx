import React from 'react';

export default function StatusBadge({ status, className = '' }) {
    const getStatusStyle = (status) => {
        switch (status) {
            case 'DRAFT': return 'bg-gray-100 text-gray-800 border-gray-200';
            case 'SUBMITTED': return 'bg-blue-50 text-blue-700 border-blue-200';
            case 'APPROVED': return 'bg-green-50 text-green-700 border-green-200';
            case 'REJECTED': return 'bg-red-50 text-red-700 border-red-200';
            default: return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${getStatusStyle(status)} ${className}`}>
            {status}
        </span>
    );
}
