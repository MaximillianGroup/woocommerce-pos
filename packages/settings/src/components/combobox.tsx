import * as React from 'react';

import { Combobox as CB, Transition } from '@headlessui/react';
import classNames from 'classnames';

import Check from '../../assets/check.svg';
import ChevronDown from '../../assets/chevron-down.svg';

export interface OptionProps extends Record<string, unknown> {
	value: string | number;
	label: string;
}

export interface ComboboxProps {
	options: OptionProps[];
	onSearch: (query: string) => void;
	onChange: (value: OptionProps) => void;
	value: string | number;
	placeholder?: string;
	loading?: boolean;
}

const Combobox = ({ options, onSearch, onChange, value, placeholder, loading }: ComboboxProps) => {
	const [query, setQuery] = React.useState('');
	const inputRef = React.useRef(null);

	/**
	 *
	 */
	const selected = React.useMemo(
		() => options.find((option) => option.value === value),
		[options, value]
	);

	/**
	 *
	 */
	const handleSearch = (event) => {
		setQuery(event.target.value);
		onSearch(event.target.value);
	};

	/**
	 *
	 */
	React.useEffect(() => {
		const handleFocus = () => {
			inputRef.current.select();
		};

		const node = inputRef.current;

		node.addEventListener('focus', handleFocus);

		return () => {
			node.removeEventListener('focus', handleFocus);
		};
	}, []);

	/**
	 *
	 */
	return (
		<CB value={selected} onChange={onChange}>
			<div className="wcpos-relative">
				<div
					className={classNames([
						'wcpos-relative',
						'wcpos-w-full',
						'wcpos-cursor-default',
						'wcpos-overflow-hidden',
						'wcpos-rounded-md',
						'wcpos-bg-white',
						'wcpos-border',
						'wcpos-border-gray-300',
						'wcpos-text-left',
						'focus:wcpos-outline-none',
						'focus-visible:wcpos-ring-2',
						'focus-visible:wcpos-ring-white',
						'focus-visible:wcpos-ring-opacity-75',
						'focus-visible:wcpos-ring-offset-2',
						'focus-visible:wcpos-ring-offset-teal-300',
						'sm:wcpos-text-sm',
					])}
				>
					<CB.Input
						ref={inputRef}
						className={classNames([
							'wcpos-w-full',
							'!wcpos-border-none',
							'wcpos-py-2',
							'wcpos-pl-3',
							'wcpos-pr-10',
							'wcpos-text-sm',
							'wcpos-leading-5',
							// 'wcpos-text-gray-900',
							'focus:wcpos-ring-0',
						])}
						displayValue={(option) => option.label}
						onChange={handleSearch}
						placeholder={placeholder}
					/>
					<CB.Button
						className={classNames([
							'wcpos-absolute',
							'wcpos-inset-y-0',
							'wcpos-right-0',
							'wcpos-flex',
							'wcpos-items-center',
							'wcpos-pr-2',
							'wcpos-bg-white',
						])}
					>
						<ChevronDown className="wcpos-h-5 w-5" aria-hidden="true" />
					</CB.Button>
				</div>
				<Transition
					as={React.Fragment}
					leave="transition ease-in duration-100"
					leaveFrom="opacity-100"
					leaveTo="opacity-0"
					afterLeave={() => setQuery('')}
				>
					<CB.Options
						className={classNames([
							'wcpos-absolute',
							'wcpos-z-10',
							'wcpos-mt-1',
							'wcpos-max-h-60',
							'wcpos-w-full',
							'wcpos-overflow-auto',
							'wcpos-rounded-md',
							'wcpos-bg-white',
							'wcpos-py-1',
							'wcpos-text-base',
							'wcpos-shadow-lg',
							'wcpos-ring-1',
							'wcpos-ring-black',
							'wcpos-ring-opacity-5',
							'focus:wcpos-outline-none',
							'sm:wcpos-text-sm',
						])}
					>
						{loading ? (
							<div className="wcpos-relative wcpos-cursor-default wcpos-select-none wcpos-py-2 wcpos-px-4 wcpos-text-gray-700">
								Loading...
							</div>
						) : options.length === 0 && query !== '' ? (
							<div className="wcpos-relative wcpos-cursor-default wcpos-select-none wcpos-py-2 wcpos-px-4 wcpos-text-gray-700">
								Nothing found.
							</div>
						) : (
							options.map((option) => (
								<CB.Option
									key={option.value}
									className={({ active }) =>
										classNames(
											'wcpos-relative',
											'wcpos-cursor-default',
											'wcpos-select-none',
											'wcpos-py-2',
											'wcpos-pl-10',
											'wcpos-pr-4',
											'wcpos-m-0',
											{ 'wcpos-bg-wp-admin-theme-color-lightest': active },
											{ 'wcpos-text-wp-admin-theme-color-darker-10': active },
											{ 'wcpos-text-gray-900': !active }
										)
									}
									value={option}
								>
									{({ selected, active }) => (
										<>
											<span
												className={`wcpos-block wcpos-truncate ${
													selected ? 'wcpos-font-medium' : 'wcpos-font-normal'
												}`}
											>
												{option.label}
											</span>
											{selected ? (
												<span
													className={classNames(
														[
															'wcpos-absolute',
															'wcpos-inset-y-0',
															'wcpos-left-0',
															'wcpos-flex',
															'wcpos-items-center',
															'wcpos-pl-3',
															'wcpos-text-wp-admin-theme-color-darker-10',
														],
														{ 'wcpos-text-white': active, 'wcpos-text-teal-600': !active }
													)}
												>
													<Check
														className="wcpos-h-5 wcpos-w-5"
														fill="#006ba1"
														aria-hidden="true"
													/>
												</span>
											) : null}
										</>
									)}
								</CB.Option>
							))
						)}
					</CB.Options>
				</Transition>
			</div>
		</CB>
	);
};

export default Combobox;
