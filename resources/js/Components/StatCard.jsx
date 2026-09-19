import React from 'react';

export default function StatCard({ title, value, subtext, icon, className = '' }) {
    return (
        <div className={`bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-start space-x-4 ${className}`}>
            {icon && (
                <div className="flex-shrink-0 bg-blue-50 text-blue-600 p-3 rounded-lg">
                    {icon}
                </div>
            )}
            <div>
                <h3 className="text-sm font-medium text-gray-500 uppercase tracking-wider">{title}</h3>
                <div className="mt-1 flex items-baseline">
                    <p className="text-2xl font-semibold text-gray-900">{value}</p>
                    {subtext && <p className="ml-2 text-sm text-gray-500">{subtext}</p>}
                </div>
            </div>
        </div>
    );
}
