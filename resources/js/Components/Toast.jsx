import React, { useEffect, useState } from 'react';

export default function Toast({ message, type = 'success', onClose }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => {
            setVisible(false);
            if (onClose) setTimeout(onClose, 300); // Wait for transition
        }, 3000);
        return () => clearTimeout(timer);
    }, [onClose]);

    if (!message) return null;

    const baseClasses = "fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 z-[60] flex items-center space-x-2";
    const typeClasses = {
        success: "bg-green-600",
        error: "bg-red-600",
        info: "bg-blue-600",
        warning: "bg-yellow-600"
    };

    return (
        <div 
            className={`${baseClasses} ${typeClasses[type]} ${visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'}`}
        >
            <span>{message}</span>
            <button onClick={() => { setVisible(false); if(onClose) onClose(); }} className="ml-4 text-white hover:text-gray-200">
                &times;
            </button>
        </div>
    );
}
