import React from 'react';

export default function SemesterBadge({ semester, className = '' }) {
    const getSemesterStyle = (sem) => {
        return sem === 'GANJIL' 
            ? 'bg-purple-50 text-purple-700 border-purple-200'
            : 'bg-teal-50 text-teal-700 border-teal-200';
    };

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${getSemesterStyle(semester)} ${className}`}>
            {semester}
        </span>
    );
}
