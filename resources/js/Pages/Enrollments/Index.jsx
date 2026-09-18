import React, { useState, useEffect } from 'react';
import { Head, router, usePage, useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';

function useDebounce(value, delay) {
    const [debouncedValue, setDebouncedValue] = useState(value);
    useEffect(() => {
        const handler = setTimeout(() => { setDebouncedValue(value); }, delay);
        return () => clearTimeout(handler);
    }, [value, delay]);
    return debouncedValue;
}

// Icons
const PlusIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
    </svg>
);
const DownloadIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
    </svg>
);
const FilterIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
    </svg>
);

const SearchableAutocomplete = ({ url, placeholder, renderLabel, valueKey, onSelect, selectedItem, clearSelection }) => {
    const [query, setQuery] = useState('');
    const debouncedQuery = useDebounce(query, 300);
    const [results, setResults] = useState([]);
    const [isOpen, setIsOpen] = useState(false);

    useEffect(() => {
        let abortController = new AbortController();

        if (debouncedQuery.length >= 2) {
            fetch(`${url}?q=${debouncedQuery}`, { signal: abortController.signal })
                .then(res => res.json())
                .then(data => {
                    setResults(data);
                    setIsOpen(true);
                })
                .catch(err => {
                    if (err.name !== 'AbortError') {
                        setResults([]);
                    }
                });
        } else {
            setResults([]);
            setIsOpen(false);
        }

        return () => {
            abortController.abort();
        };
    }, [debouncedQuery, url]);

    if (selectedItem) {
        return (
            <div className="flex items-center gap-2 bg-blue-50 dark:bg-gray-700 border border-blue-200 dark:border-gray-600 p-2 rounded-md shadow-sm">
                <span className="flex-1 font-medium text-blue-800 dark:text-blue-200">{renderLabel(selectedItem)}</span>
                <button type="button" onClick={clearSelection} className="text-gray-500 hover:text-red-600 font-bold p-1 bg-white dark:bg-gray-600 rounded w-6 h-6 flex items-center justify-center">&times;</button>
            </div>
        );
    }

    return (
        <div className="relative">
            <TextInput
                className="w-full shadow-sm"
                placeholder={placeholder}
                value={query}
                onChange={e => { setQuery(e.target.value); setIsOpen(true); }}
                onFocus={() => { if (results.length > 0) setIsOpen(true); }}
                onBlur={() => setTimeout(() => setIsOpen(false), 200)}
            />
            {isOpen && results.length > 0 && (
                <ul className="absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 mt-1 rounded-md shadow-lg max-h-60 overflow-auto">
                    {results.map(item => (
                        <li key={item[valueKey]}
                            className="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm text-gray-800 dark:text-gray-200"
                            onMouseDown={(e) => {
                                e.preventDefault(); // Mencegah input kehilangan fokus terlalu cepat
                                onSelect(item);
                                setQuery('');
                                setIsOpen(false);
                            }}>
                            {renderLabel(item)}
                        </li>
                    ))}
                </ul>
            )}
            {isOpen && query.length >= 2 && results.length === 0 && (
                <div className="absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 mt-1 rounded-md shadow-lg px-4 py-2 text-sm text-gray-500">Tidak ada hasil ditemukan.</div>
            )}
        </div>
    );
};

export default function Index({ enrollments, filters, statusCounts = {} }) {
    const { errors: pageErrors, flash } = usePage().props;

    // Table State
    const [search, setSearch] = useState(filters.search || '');
    const debouncedSearch = useDebounce(search, 500);
    const [status, setStatus] = useState(filters.status || '');
    const [semester, setSemester] = useState(filters.semester || '');
    const [pageSize, setPageSize] = useState(filters.page_size || 15);
    const [sortBy, setSortBy] = useState(filters.sort_by || 'id');
    const [sortDir, setSortDir] = useState(filters.sort_dir || 'desc');
    // Multi-kolom sort: array of {col, dir}
    const [sortOrders, setSortOrders] = useState(
        filters.sort_orders ? JSON.parse(filters.sort_orders) : []
    );

    // Adv Filter State
    const [isAdvOpen, setIsAdvOpen] = useState(false);
    const [advFilters, setAdvFilters] = useState(filters.filters ? JSON.parse(filters.filters) : []);
    const [filterLogic, setFilterLogic] = useState(filters.filter_logic || 'and');

    const applyFilters = (overrides = {}) => {
        router.get(route('enrollments.index'), {
            search,
            status,
            semester,
            page_size: pageSize,
            sort_by: sortBy,
            sort_dir: sortDir,
            sort_orders: sortOrders.length > 0 ? JSON.stringify(sortOrders) : undefined,
            filters: JSON.stringify(advFilters),
            filter_logic: filterLogic,
            ...overrides,
        }, { preserveState: true, replace: true });
    };

    useEffect(() => {
        if (debouncedSearch !== (filters.search || '')) {
            applyFilters({ search: debouncedSearch, page: 1 });
        }
    }, [debouncedSearch]);

    // Single-column header click sort (Ctrl+Click = multi-column tambah/hapus)
    const handleSort = (field, event) => {
        if (event && event.ctrlKey) {
            // Multi-kolom: toggle field ke dalam sortOrders
            setSortOrders(prev => {
                const existing = prev.findIndex(o => o.col === field);
                let next;
                if (existing === -1) {
                    next = [...prev, { col: field, dir: 'asc' }];
                } else if (prev[existing].dir === 'asc') {
                    next = prev.map((o, i) => i === existing ? { ...o, dir: 'desc' } : o);
                } else {
                    next = prev.filter((_, i) => i !== existing);
                }
                applyFilters({ sort_orders: next.length > 0 ? JSON.stringify(next) : undefined, sort_by: undefined, page: 1 });
                return next;
            });
        } else {
            // Single-column mode: reset multi
            setSortOrders([]);
            let newDir = 'asc';
            if (sortBy === field && sortDir === 'asc') newDir = 'desc';
            setSortBy(field);
            setSortDir(newDir);
            applyFilters({ sort_by: field, sort_dir: newDir, sort_orders: undefined, page: 1 });
        }
    };

    // Helper: tampilkan indikator sort di header
    const getSortIndicator = (field) => {
        if (sortOrders.length > 0) {
            const idx = sortOrders.findIndex(o => o.col === field);
            if (idx === -1) return '';
            const dir = sortOrders[idx].dir === 'asc' ? '↑' : '↓';
            return ` ${dir}${idx + 1}`; // tampilkan urutan sort
        }
        if (sortBy === field) return sortDir === 'asc' ? ' ↑' : ' ↓';
        return '';
    };

    const handleExport = () => {
        const url = route('enrollments.export', {
            search, status, semester, sort_by: sortBy, sort_dir: sortDir,
            filters: JSON.stringify(advFilters), filter_logic: filterLogic
        });
        window.location.href = url;
    };

    // Form State
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [selectedStudent, setSelectedStudent] = useState(null);
    const [selectedCourse, setSelectedCourse] = useState(null);

    const { data, setData, post, put, reset, errors, clearErrors } = useForm({
        student_id: '', student_nim: '', student_name: '', student_email: '',
        course_id: '', course_code: '', course_name: '', course_credits: '',
        academic_year: '', semester: 'GANJIL', status: 'DRAFT',
    });

    const openCreate = () => {
        clearErrors();
        reset();
        setEditingId(null);
        setSelectedStudent(null);
        setSelectedCourse(null);
        setIsFormOpen(true);
    };

    const openEdit = (item) => {
        clearErrors();
        setEditingId(item.id);
        setData({
            student_id: item.student_id, student_nim: '', student_name: '', student_email: '',
            course_id: item.course_id, course_code: '', course_name: '', course_credits: '',
            academic_year: item.academic_year, semester: item.semester, status: item.status,
        });
        setIsFormOpen(true);
    };

    const submitForm = (e) => {
        e.preventDefault();
        if (editingId) {
            put(route('enrollments.update', editingId), { onSuccess: () => setIsFormOpen(false) });
        } else {
            post(route('enrollments.store'), { onSuccess: () => setIsFormOpen(false) });
        }
    };

    const deleteItem = (id) => {
        if (confirm("Apakah Anda yakin ingin menghapus data KRS ini?")) {
            router.delete(route('enrollments.destroy', id), { preserveState: true });
        }
    };

    // Rendering Badges
    const renderStatusBadge = (statusValue) => {
        const styles = {
            'DRAFT': 'bg-gray-200 text-gray-800',
            'SUBMITTED': 'bg-blue-100 text-blue-800',
            'APPROVED': 'bg-green-100 text-green-800',
            'REJECTED': 'bg-red-100 text-red-800'
        };
        return (
            <span className={`px-2 py-1 text-xs font-semibold rounded-full ${styles[statusValue] || 'bg-gray-100 text-gray-800'}`}>
                {statusValue}
            </span>
        );
    };

    const renderSemesterBadge = (semesterValue) => {
        const styles = {
            'GANJIL': 'bg-orange-100 text-orange-800 border border-orange-200',
            'GENAP': 'bg-purple-100 text-purple-800 border border-purple-200'
        };
        return (
            <span className={`px-2 py-1 text-xs font-semibold rounded-md ${styles[semesterValue] || 'bg-gray-100 text-gray-800'}`}>
                {semesterValue}
            </span>
        );
    };

    // Calculate Summary Totals
    const totalDraft = statusCounts['DRAFT'] || 0;
    const totalSubmitted = statusCounts['SUBMITTED'] || 0;
    const totalApproved = statusCounts['APPROVED'] || 0;
    const totalRejected = statusCounts['REJECTED'] || 0;
    const totalAll = totalDraft + totalSubmitted + totalApproved + totalRejected;

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900 pb-12">
            <Head title="Kartu Rencana Studi (KRS)" />

            {/* Header */}
            <header className="bg-white shadow dark:bg-gray-800 mb-8">
                <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Kartu Rencana Studi (KRS)</h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola data pengambilan mata kuliah mahasiswa secara terpusat.</p>
                </div>
            </header>

            <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                {flash?.success && <div className="mb-6 text-green-700 bg-green-50 border border-green-200 p-4 rounded-md shadow-sm">{flash.success}</div>}
                {pageErrors?.general && <div className="mb-6 text-red-700 bg-red-50 border border-red-200 p-4 rounded-md shadow-sm">{pageErrors.general}</div>}

                {/* Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Total KRS</p>
                        <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{totalAll.toLocaleString('id-ID')}</p>
                    </div>
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 border-l-4 border-l-blue-500">
                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Menunggu (Submitted)</p>
                        <p className="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">{totalSubmitted.toLocaleString('id-ID')}</p>
                    </div>
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 border-l-4 border-l-green-500">
                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Disetujui (Approved)</p>
                        <p className="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">{totalApproved.toLocaleString('id-ID')}</p>
                    </div>
                    <div className="bg-white dark:bg-gray-800 p-5 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 border-l-4 border-l-red-500">
                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Ditolak (Rejected)</p>
                        <p className="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">{totalRejected.toLocaleString('id-ID')}</p>
                    </div>
                </div>

                {/* Main Content Box */}
                <div className="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 border border-gray-100 dark:border-gray-700">

                    {/* Toolbar / Filters */}
                    <div className="flex flex-col md:flex-row gap-4 mb-6 justify-between items-start md:items-center">
                        <div className="flex flex-wrap gap-2 w-full md:w-auto flex-1">
                            <TextInput
                                className="w-full md:w-72 shadow-sm"
                                placeholder="Cari NIM, nama mahasiswa, atau kode MK..."
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                            />
                            <select className="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm" value={status} onChange={e => { setStatus(e.target.value); applyFilters({ status: e.target.value, page: 1 }); }}>
                                <option value="">Semua Status</option>
                                <option value="DRAFT">Draft</option>
                                <option value="SUBMITTED">Submitted</option>
                                <option value="APPROVED">Approved</option>
                                <option value="REJECTED">Rejected</option>
                            </select>
                            <select className="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm text-sm" value={semester} onChange={e => { setSemester(e.target.value); applyFilters({ semester: e.target.value, page: 1 }); }}>
                                <option value="">Semua Semester</option>
                                <option value="GANJIL">Ganjil</option>
                                <option value="GENAP">Genap</option>
                            </select>
                            <SecondaryButton onClick={() => setIsAdvOpen(true)} className="shadow-sm">
                                <FilterIcon /> Filter Lanjutan {advFilters.length > 0 && `(${advFilters.length})`}
                            </SecondaryButton>
                        </div>
                        <div className="flex gap-2 w-full md:w-auto shrink-0 justify-end mt-4 md:mt-0">
                            <SecondaryButton onClick={handleExport} className="shadow-sm border-gray-300">
                                <DownloadIcon /> Ekspor Data
                            </SecondaryButton>
                            <PrimaryButton onClick={openCreate} className="bg-blue-600 hover:bg-blue-700 shadow-sm">
                                <PlusIcon /> Tambah KRS
                            </PrimaryButton>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table className="w-full text-left border-collapse text-sm text-gray-800 dark:text-gray-200 whitespace-nowrap">
                            <thead className="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                <tr>
                                <th className="p-3 font-semibold border-b dark:border-gray-600 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600 transition select-none" onClick={(e) => handleSort('id', e)} title="Ctrl+Click untuk sort multi-kolom">ID{getSortIndicator('id')}</th>
                                    <th className="p-3 font-semibold border-b dark:border-gray-600">NIM</th>
                                    <th className="p-3 font-semibold border-b dark:border-gray-600">Nama Mahasiswa</th>
                                    <th className="p-3 font-semibold border-b dark:border-gray-600">Mata Kuliah</th>
                                    <th className="p-3 font-semibold border-b dark:border-gray-600 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600 transition select-none" onClick={(e) => handleSort('academic_year', e)} title="Ctrl+Click untuk sort multi-kolom">Tahun Ajaran{getSortIndicator('academic_year')}</th>
                                    <th className="p-3 font-semibold border-b dark:border-gray-600 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600 transition select-none" onClick={(e) => handleSort('semester', e)} title="Ctrl+Click untuk sort multi-kolom">Semester{getSortIndicator('semester')}</th>
                                    <th className="p-3 font-semibold border-b dark:border-gray-600 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600 transition select-none" onClick={(e) => handleSort('status', e)} title="Ctrl+Click untuk sort multi-kolom">Status{getSortIndicator('status')}</th>
                                    <th className="p-3 font-semibold border-b dark:border-gray-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {enrollments.data.map(item => (
                                    <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                        <td className="p-3 text-gray-500">{item.id}</td>
                                        <td className="p-3 font-medium text-gray-900 dark:text-white">{item.student?.nim}</td>
                                        <td className="p-3">{item.student?.name}</td>
                                        <td className="p-3">
                                            <span className="font-medium text-indigo-600 dark:text-indigo-400">{item.course?.code}</span>
                                            <span className="text-gray-500 ml-1">({item.course?.name})</span>
                                        </td>
                                        <td className="p-3">{item.academic_year}</td>
                                        <td className="p-3">{renderSemesterBadge(item.semester)}</td>
                                        <td className="p-3">{renderStatusBadge(item.status)}</td>
                                        <td className="p-3 flex gap-3">
                                            <button onClick={() => openEdit(item)} className="font-medium text-blue-600 hover:text-blue-800 dark:hover:text-blue-400 transition">Ubah</button>
                                            <button onClick={() => deleteItem(item.id)} className="font-medium text-red-600 hover:text-red-800 dark:hover:text-red-400 transition">Hapus</button>
                                        </td>
                                    </tr>
                                ))}
                                {enrollments.data.length === 0 && (
                                    <tr><td colSpan="8" className="p-8 text-center text-gray-500">Data tidak ditemukan.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    <div className="mt-6 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-600 dark:text-gray-400 gap-4">
                        <div className="flex items-center">
                            Menampilkan data halaman ini
                            <select className="ml-4 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md text-sm py-1 pl-2 pr-8 shadow-sm" value={pageSize} onChange={e => { setPageSize(e.target.value); applyFilters({ page_size: e.target.value, page: 1 }); }}>
                                <option value="15">15 per halaman</option>
                                <option value="50">50 per halaman</option>
                                <option value="100">100 per halaman</option>
                            </select>
                        </div>
                        <div className="flex gap-2 flex-wrap justify-center">
                            <button 
                                onClick={() => enrollments.prev_page_url && router.get(enrollments.prev_page_url, {}, {preserveState:true})}
                                disabled={!enrollments.prev_page_url}
                                className={`px-4 py-2 border rounded-md text-sm font-medium transition-colors ${!enrollments.prev_page_url ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'}`}
                            >
                                &laquo; Sebelumnya
                            </button>
                            <button 
                                onClick={() => enrollments.next_page_url && router.get(enrollments.next_page_url, {}, {preserveState:true})}
                                disabled={!enrollments.next_page_url}
                                className={`px-4 py-2 border rounded-md text-sm font-medium transition-colors ${!enrollments.next_page_url ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'}`}
                            >
                                Selanjutnya &raquo;
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Adv Filter Modal */}
            <Modal show={isAdvOpen} onClose={() => setIsAdvOpen(false)}>
                <div className="p-6 text-gray-900 dark:text-gray-100">
                    <h2 className="text-xl font-bold mb-4 border-b pb-2">Filter Lanjutan</h2>
                    <div className="mb-4">
                        <label className="mr-4 font-medium">Logika Gabungan:</label>
                        <select value={filterLogic} onChange={e => setFilterLogic(e.target.value)} className="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="and">Cocokkan SEMUA (AND)</option>
                            <option value="or">Cocokkan SALAH SATU (OR)</option>
                        </select>
                    </div>
                    {advFilters.map((f, i) => (
                        <div key={i} className="flex gap-2 mb-3 items-center flex-wrap bg-gray-50 dark:bg-gray-700 p-2 rounded border border-gray-200 dark:border-gray-600">
                            <select value={f.field} onChange={e => {
                                const nf = [...advFilters]; nf[i].field = e.target.value; setAdvFilters(nf);
                            }} className="border-gray-300 rounded-md h-10 px-2 text-sm shadow-sm flex-1 min-w-[120px]">
                                <option value="">Pilih Kolom</option>
                                <option value="nim">NIM</option>
                                <option value="name">Nama Mahasiswa</option>
                                <option value="code">Kode MK</option>
                                <option value="academic_year">Tahun Ajaran</option>
                            </select>
                            <select value={f.operator} onChange={e => {
                                const nf = [...advFilters]; nf[i].operator = e.target.value; setAdvFilters(nf);
                            }} className="border-gray-300 rounded-md h-10 px-2 text-sm shadow-sm flex-1 min-w-[120px]">
                                <option value="equal">Sama Dengan (=)</option>
                                <option value="contains">Mengandung</option>
                                <option value="startsWith">Berawalan</option>
                            </select>
                            <TextInput className="h-10 flex-1 min-w-[150px] shadow-sm text-sm" value={f.value} placeholder="Nilai filter..." onChange={e => {
                                const nf = [...advFilters]; nf[i].value = e.target.value; setAdvFilters(nf);
                            }} />
                            <DangerButton onClick={() => setAdvFilters(advFilters.filter((_, idx) => idx !== i))} className="h-10 px-3 shrink-0">X</DangerButton>
                        </div>
                    ))}
                    <SecondaryButton onClick={() => setAdvFilters([...advFilters, { field: '', operator: 'equal', value: '' }])} className="mb-4 mt-2 border-dashed">
                        + Tambah Kondisi
                    </SecondaryButton>
                    <div className="flex justify-end gap-2 border-t pt-4 mt-4">
                        <SecondaryButton onClick={() => { setAdvFilters([]); applyFilters({ filters: '[]', page: 1 }); setIsAdvOpen(false); }}>Reset</SecondaryButton>
                        <PrimaryButton onClick={() => { applyFilters({ filters: JSON.stringify(advFilters), filter_logic: filterLogic, page: 1 }); setIsAdvOpen(false); }} className="bg-blue-600">Terapkan Filter</PrimaryButton>
                    </div>
                </div>
            </Modal>

            {/* Form Modal Create/Edit */}
            <Modal show={isFormOpen} onClose={() => setIsFormOpen(false)}>
                <form onSubmit={submitForm} className="p-6 text-gray-900 dark:text-gray-100 max-h-[85vh] overflow-y-auto">
                    <h2 className="text-xl font-bold mb-4 border-b pb-2">{editingId ? 'Ubah Data KRS' : 'Tambah KRS Baru'}</h2>

                    {!editingId && (
                        <>
                            <h3 className="font-semibold text-blue-700 bg-blue-50 p-2 rounded mt-4 mb-2">Informasi Mahasiswa</h3>
                            <div className="mb-4 bg-gray-50 dark:bg-gray-700 p-3 rounded-md border border-gray-200 dark:border-gray-600">
                                <InputLabel value="Cari Mahasiswa Terdaftar (opsional)" className="mb-1" />
                                <SearchableAutocomplete
                                    url="/students/search"
                                    placeholder="Ketik NIM atau nama mahasiswa..."
                                    valueKey="id"
                                    renderLabel={(item) => `${item.nim} - ${item.name}`}
                                    selectedItem={selectedStudent}
                                    onSelect={(item) => {
                                        setSelectedStudent(item);
                                        setData('student_id', item.id);
                                    }}
                                    clearSelection={() => {
                                        setSelectedStudent(null);
                                        setData('student_id', '');
                                    }}
                                />
                                {errors.student_id && <div className="text-red-500 text-xs mt-1">{errors.student_id}</div>}
                            </div>

                            <div className="flex items-center my-4">
                                <div className="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                                <div className="px-3 text-xs font-semibold text-gray-500 uppercase">ATAU BUAT DATA BARU DI BAWAH INI</div>
                                <div className="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                            </div>

                            <div className={`transition-opacity duration-200 ${selectedStudent ? 'opacity-40 pointer-events-none' : ''}`}>
                                <div className="mb-3">
                                    <InputLabel value="NIM Baru (8-12 angka)" />
                                    <TextInput value={data.student_nim} onChange={e => setData('student_nim', e.target.value)} className="w-full shadow-sm" disabled={!!selectedStudent} />
                                    {errors.student_nim && <div className="text-red-500 text-xs mt-1">{errors.student_nim}</div>}
                                </div>
                                <div className="mb-3">
                                    <InputLabel value="Nama Baru" />
                                    <TextInput value={data.student_name} onChange={e => setData('student_name', e.target.value)} className="w-full shadow-sm" disabled={!!selectedStudent} />
                                    {errors.student_name && <div className="text-red-500 text-xs mt-1">{errors.student_name}</div>}
                                </div>
                                <div className="mb-4">
                                    <InputLabel value="Email Baru" />
                                    <TextInput value={data.student_email} onChange={e => setData('student_email', e.target.value)} className="w-full shadow-sm" disabled={!!selectedStudent} />
                                    {errors.student_email && <div className="text-red-500 text-xs mt-1">{errors.student_email}</div>}
                                </div>
                            </div>

                            <h3 className="font-semibold text-indigo-700 bg-indigo-50 p-2 rounded mt-6 mb-2">Informasi Mata Kuliah</h3>
                            <div className="mb-4 bg-gray-50 dark:bg-gray-700 p-3 rounded-md border border-gray-200 dark:border-gray-600">
                                <InputLabel value="Cari Mata Kuliah Terdaftar (opsional)" className="mb-1" />
                                <SearchableAutocomplete
                                    url="/courses/search"
                                    placeholder="Ketik kode atau nama MK..."
                                    valueKey="id"
                                    renderLabel={(item) => `${item.code} - ${item.name}`}
                                    selectedItem={selectedCourse}
                                    onSelect={(item) => {
                                        setSelectedCourse(item);
                                        setData('course_id', item.id);
                                    }}
                                    clearSelection={() => {
                                        setSelectedCourse(null);
                                        setData('course_id', '');
                                    }}
                                />
                                {errors.course_id && <div className="text-red-500 text-xs mt-1">{errors.course_id}</div>}
                            </div>

                            <div className="flex items-center my-4">
                                <div className="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                                <div className="px-3 text-xs font-semibold text-gray-500 uppercase">ATAU BUAT DATA BARU DI BAWAH INI</div>
                                <div className="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                            </div>

                            <div className={`transition-opacity duration-200 ${selectedCourse ? 'opacity-40 pointer-events-none' : ''}`}>
                                <div className="mb-3">
                                    <InputLabel value="Kode MK Baru (contoh: CS101)" />
                                    <TextInput value={data.course_code} onChange={e => setData('course_code', e.target.value)} className="w-full shadow-sm" disabled={!!selectedCourse} />
                                    {errors.course_code && <div className="text-red-500 text-xs mt-1">{errors.course_code}</div>}
                                </div>
                                <div className="mb-3">
                                    <InputLabel value="Nama MK Baru" />
                                    <TextInput value={data.course_name} onChange={e => setData('course_name', e.target.value)} className="w-full shadow-sm" disabled={!!selectedCourse} />
                                    {errors.course_name && <div className="text-red-500 text-xs mt-1">{errors.course_name}</div>}
                                </div>
                                <div className="mb-4">
                                    <InputLabel value="SKS (1-6)" />
                                    <TextInput type="number" value={data.course_credits} onChange={e => setData('course_credits', e.target.value)} className="w-full shadow-sm" disabled={!!selectedCourse} />
                                    {errors.course_credits && <div className="text-red-500 text-xs mt-1">{errors.course_credits}</div>}
                                </div>
                            </div>
                        </>
                    )}

                    <h3 className="font-semibold text-green-700 bg-green-50 p-2 rounded mt-6 mb-2">Detail KRS</h3>
                    <div className="mb-3">
                        <InputLabel value="Tahun Ajaran (contoh: 2024/2025)" />
                        <TextInput value={data.academic_year} onChange={e => setData('academic_year', e.target.value)} className="w-full shadow-sm" />
                        {errors.academic_year && <div className="text-red-500 text-xs mt-1">{errors.academic_year}</div>}
                    </div>
                    <div className="mb-3">
                        <InputLabel value="Semester" />
                        <select value={data.semester} onChange={e => setData('semester', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm">
                            <option value="GANJIL">GANJIL</option>
                            <option value="GENAP">GENAP</option>
                        </select>
                        {errors.semester && <div className="text-red-500 text-xs mt-1">{errors.semester}</div>}
                    </div>
                    <div className="mb-4 mt-3">
                        <InputLabel value="Status" />
                        <select value={data.status} onChange={e => setData('status', e.target.value)} className="w-full border-gray-300 rounded-md shadow-sm">
                            <option value="DRAFT">DRAFT</option>
                            <option value="SUBMITTED">SUBMITTED</option>
                            <option value="APPROVED">APPROVED</option>
                            <option value="REJECTED">REJECTED</option>
                        </select>
                        {errors.status && <div className="text-red-500 text-xs mt-1">{errors.status}</div>}
                    </div>

                    <div className="flex justify-end gap-3 border-t pt-5 mt-6 bg-gray-50 dark:bg-gray-800 -mx-6 -mb-6 px-6 pb-6 rounded-b">
                        <SecondaryButton type="button" onClick={() => setIsFormOpen(false)}>Batal</SecondaryButton>
                        <PrimaryButton type="submit" className="bg-blue-600">Simpan Data</PrimaryButton>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
