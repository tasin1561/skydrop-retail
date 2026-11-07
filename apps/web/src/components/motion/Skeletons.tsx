// Route loading skeletons (used by loading.tsx files). They fade in only after 150ms (.mo-skel), so a
// fast navigation never flashes them; shimmers pause off-screen ([data-loop]). Each mirrors its page.

function Shell({ label, children, className }: { label: string; children: React.ReactNode; className?: string }) {
  return (
    <div className={"mo-skel " + (className ?? "")} aria-busy="true" data-loop="">
      <span className="sr-only" role="status">
        {label}
      </span>
      <div aria-hidden="true">{children}</div>
    </div>
  );
}

const B = ({ c }: { c: string }) => <div className={"skeleton " + c} />;

function CardSk() {
  return (
    <div className="overflow-hidden rounded-2xl bg-surface shadow-[var(--shadow-1)]">
      <B c="aspect-square" />
      <div className="space-y-2 p-3">
        <B c="h-4 w-20 rounded-full" />
        <B c="h-3.5 w-full rounded-md" />
        <B c="h-3.5 w-3/4 rounded-md" />
        <B c="mt-3 h-5 w-24 rounded-md" />
      </div>
    </div>
  );
}

function RailSk() {
  return (
    <section className="mt-8">
      <div className="mb-3 flex items-end justify-between px-4 sm:px-0">
        <B c="h-6 w-44 rounded-lg" />
        <B c="h-4 w-14 rounded-md" />
      </div>
      <div className="flex gap-3 overflow-hidden px-4 sm:px-0">
        {[0, 1, 2, 3, 4, 5].map((i) => (
          <div key={i} className="w-[44vw] max-w-[220px] shrink-0 sm:w-[200px]">
            <CardSk />
          </div>
        ))}
      </div>
    </section>
  );
}

function GridSk({ n = 10 }: { n?: number }) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
      {Array.from({ length: n }, (_, i) => (
        <CardSk key={i} />
      ))}
    </div>
  );
}

export function HomeSkeleton() {
  return (
    <Shell label="Loading the store" className="mx-auto max-w-7xl pt-3 sm:px-4">
      <div className="px-4 sm:px-0">
        <B c="h-[300px] rounded-[28px] sm:h-[360px]" />
      </div>
      <div className="mt-4 flex gap-2 overflow-hidden px-4 sm:px-0">
        {[0, 1, 2, 3, 4, 5, 6].map((i) => (
          <B key={i} c="h-10 w-24 shrink-0 rounded-full" />
        ))}
      </div>
      <div className="mt-6 px-4 sm:px-0">
        <B c="h-48 rounded-[24px]" />
      </div>
      <RailSk />
      <RailSk />
    </Shell>
  );
}

export function StoreSkeleton() {
  return (
    <Shell label="Loading the store" className="mx-auto max-w-7xl pt-3 sm:px-4">
      <div className="px-4 sm:px-0">
        <B c="h-[260px] rounded-[28px] sm:h-[320px]" />
      </div>
      <div className="mt-4 flex gap-2 overflow-hidden px-4 sm:px-0">
        {[0, 1, 2, 3, 4, 5].map((i) => (
          <B key={i} c="h-10 w-24 shrink-0 rounded-full" />
        ))}
      </div>
      <RailSk />
      <RailSk />
    </Shell>
  );
}

export function SearchSkeleton() {
  return (
    <Shell label="Searching Indian stores" className="mx-auto max-w-7xl px-4 pb-24 pt-4 md:pb-8">
      <div className="mb-4 space-y-2">
        <B c="h-3.5 w-40 rounded-md" />
        <B c="h-7 w-64 max-w-full rounded-lg" />
        <B c="h-4 w-72 max-w-full rounded-md" />
        <div className="flex gap-2 pt-2">
          {[0, 1, 2, 3].map((i) => (
            <B key={i} c="h-10 w-24 rounded-full" />
          ))}
        </div>
      </div>
      <div className="flex gap-6">
        <div className="hidden w-60 shrink-0 space-y-3 lg:block">
          <B c="h-64 rounded-[20px]" />
          <B c="h-40 rounded-[20px]" />
        </div>
        <div className="min-w-0 flex-1">
          <GridSk />
        </div>
      </div>
    </Shell>
  );
}

export function ProductSkeleton() {
  return (
    <Shell label="Loading product" className="mx-auto max-w-7xl pb-8 sm:px-6 lg:px-8">
      <div className="px-4 pb-2 pt-3 sm:px-0 sm:pt-5">
        <B c="h-4 w-56 max-w-full rounded-md" />
      </div>
      <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-10">
        <div className="lg:flex lg:gap-4">
          <div className="hidden w-16 shrink-0 flex-col gap-2 lg:flex">
            {[0, 1, 2, 3, 4].map((i) => (
              <B key={i} c="h-16 w-16 rounded-[14px]" />
            ))}
          </div>
          <B c="aspect-square flex-1 lg:rounded-[20px]" />
        </div>
        <div className="space-y-3 px-4 pt-5 sm:px-0 lg:pt-0">
          <B c="h-5 w-32 rounded-full" />
          <B c="h-6 w-full rounded-lg" />
          <B c="h-6 w-4/5 rounded-lg" />
          <B c="h-4 w-40 rounded-md" />
          <B c="mt-4 h-9 w-44 rounded-lg" />
          <B c="h-24 rounded-2xl" />
          <div className="grid grid-cols-2 gap-2.5 pt-2">
            <B c="h-[52px] rounded-full" />
            <B c="h-[52px] rounded-full" />
          </div>
          <B c="mt-3 h-40 rounded-[16px]" />
        </div>
      </div>
    </Shell>
  );
}

export function AccountSkeleton() {
  return (
    <Shell label="Loading your account" className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <div className="flex items-center gap-3">
        <B c="h-14 w-14 rounded-2xl" />
        <div className="flex-1 space-y-2">
          <B c="h-6 w-40 rounded-lg" />
          <B c="h-4 w-56 max-w-full rounded-md" />
        </div>
      </div>
      <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[0, 1, 2, 3].map((i) => (
          <B key={i} c="h-24 rounded-[20px]" />
        ))}
      </div>
      <div className="mt-5 space-y-3">
        {[0, 1, 2, 3, 4].map((i) => (
          <B key={i} c="h-16 rounded-[18px]" />
        ))}
      </div>
    </Shell>
  );
}

export function OrdersSkeleton() {
  return (
    <Shell label="Loading your orders" className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <div className="flex items-center gap-3">
        <B c="h-12 w-12 rounded-2xl" />
        <div className="flex-1 space-y-2">
          <B c="h-6 w-40 rounded-lg" />
          <B c="h-4 w-52 max-w-full rounded-md" />
        </div>
      </div>
      <B c="mt-5 h-11 rounded-full" />
      <div className="mt-4 space-y-3">
        {[0, 1, 2].map((i) => (
          <B key={i} c="h-40 rounded-[18px]" />
        ))}
      </div>
    </Shell>
  );
}

export function PageSkeleton() {
  return (
    <Shell label="Loading" className="mx-auto max-w-6xl px-4 pb-24 pt-6">
      <B c="h-8 w-48 rounded-xl" />
      <B c="mt-3 h-4 w-72 max-w-full rounded-md" />
      <div className="mt-6 grid gap-4 md:grid-cols-[1fr_340px]">
        <B c="h-64 rounded-[20px]" />
        <B c="hidden h-64 rounded-[20px] md:block" />
      </div>
    </Shell>
  );
}
